<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGiftInventory extends Model
{
    use HasFactory;

    protected $table = 'user_gift_inventory';

    protected $fillable = [
        'user_id',
        'gift_id',
        'quantity',
        'received_from_user_id',
        'received_at',
        'is_converted',
        'converted_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'converted_at' => 'datetime',
        'is_converted' => 'boolean',
    ];

    protected $appends = [
        'total_value',
        'formatted_received_date',
        'formatted_converted_date'
    ];

    /**
     * Get the user who owns this gift
     */
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    /**
     * Get the gift details
     */
    public function gift()
    {
        return $this->belongsTo(Gifts::class, 'gift_id');
    }

    /**
     * Get the user who sent this gift
     */
    public function sender()
    {
        return $this->belongsTo(Users::class, 'received_from_user_id');
    }

    /**
     * Scope to get only unconverted gifts
     */
    public function scopeUnconverted($query)
    {
        return $query->where('is_converted', false);
    }

    /**
     * Scope to get only converted gifts
     */
    public function scopeConverted($query)
    {
        return $query->where('is_converted', true);
    }

    /**
     * Scope to get gifts by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->whereHas('gift', function ($q) use ($category) {
            $q->where('category', $category);
        });
    }

    /**
     * Scope to get gifts by rarity
     */
    public function scopeByRarity($query, $rarity)
    {
        return $query->whereHas('gift', function ($q) use ($rarity) {
            $q->where('rarity', $rarity);
        });
    }

    /**
     * Calculate total coin value for this inventory item
     */
    public function getTotalValueAttribute()
    {
        return $this->quantity * ($this->gift->coin_price ?? 0);
    }

    /**
     * Get formatted received date
     */
    public function getFormattedReceivedDateAttribute()
    {
        return $this->received_at ? $this->received_at->format('M d, Y H:i') : null;
    }

    /**
     * Get formatted converted date
     */
    public function getFormattedConvertedDateAttribute()
    {
        return $this->converted_at ? $this->converted_at->format('M d, Y H:i') : null;
    }

    /**
     * Static method to add gift to inventory
     */
    public static function addGift($userId, $giftId, $senderId = null, $quantity = 1)
    {
        // Check if same gift from same sender already exists (to combine quantities)
        $existingGift = self::where([
            'user_id' => $userId,
            'gift_id' => $giftId,
            'received_from_user_id' => $senderId,
            'is_converted' => false
        ])->first();

        if ($existingGift) {
            // Add to existing quantity
            $existingGift->quantity += $quantity;
            $existingGift->received_at = now(); // Update received time
            $existingGift->save();
            return $existingGift;
        } else {
            // Create new inventory item
            return self::create([
                'user_id' => $userId,
                'gift_id' => $giftId,
                'quantity' => $quantity,
                'received_from_user_id' => $senderId,
                'received_at' => now(),
            ]);
        }
    }

    /**
     * Static method to convert gifts to coins
     */
    public static function convertGifts($inventoryIds, $userId)
    {
        $gifts = self::whereIn('id', $inventoryIds)
            ->where('user_id', $userId)
            ->where('is_converted', false)
            ->with('gift')
            ->get();

        if ($gifts->isEmpty()) {
            return ['success' => false, 'message' => 'No valid gifts found to convert'];
        }

        $totalCoins = 0;
        $convertedItems = [];

        \DB::beginTransaction();
        try {
            foreach ($gifts as $gift) {
                $coinValue = $gift->quantity * $gift->gift->coin_price;
                $totalCoins += $coinValue;

                // Mark as converted
                $gift->is_converted = true;
                $gift->converted_at = now();
                $gift->save();

                $convertedItems[] = [
                    'gift_name' => $gift->gift->name,
                    'quantity' => $gift->quantity,
                    'coin_value' => $coinValue
                ];
            }

            // Add coins to user wallet
            $user = Users::find($userId);
            if ($user) {
                $user->wallet = ($user->wallet ?? 0) + $totalCoins;
                $user->save();
            }

            \DB::commit();
            
            return [
                'success' => true, 
                'message' => 'Gifts converted successfully',
                'total_coins' => $totalCoins,
                'converted_items' => $convertedItems
            ];

        } catch (\Exception $e) {
            \DB::rollBack();
            return ['success' => false, 'message' => 'Failed to convert gifts: ' . $e->getMessage()];
        }
    }
}