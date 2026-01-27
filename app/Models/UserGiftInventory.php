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
        'received_from_user_id' => 'array',
    ];

    protected $appends = [
        'total_value',
        'formatted_received_date',
        'formatted_converted_date',
        'sender_count',
        'sender_names'
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
     * Get all users who sent this gift (many-to-many via JSON array)
     */
    public function senders()
    {
        $senderIds = $this->received_from_user_id ?? [];
        if (empty($senderIds)) {
            return collect();
        }
        return Users::whereIn('id', $senderIds)->with('images')->get();
    }

    /**
     * Get the first/primary sender (for backward compatibility)
     */
    public function sender()
    {
        $senderIds = $this->received_from_user_id ?? [];
        if (empty($senderIds)) {
            return null;
        }
        return Users::with('images')->find($senderIds[0]);
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
     * Get count of unique senders
     */
    public function getSenderCountAttribute()
    {
        $senderIds = $this->received_from_user_id ?? [];
        return count(array_unique($senderIds));
    }

    /**
     * Get names of all senders
     */
    public function getSenderNamesAttribute()
    {
        $senderIds = $this->received_from_user_id ?? [];
        if (empty($senderIds)) {
            return 'Unknown sender';
        }
        
        $senders = Users::whereIn('id', $senderIds)->pluck('fullname')->toArray();
        
        if (count($senders) === 1) {
            return $senders[0];
        } elseif (count($senders) === 2) {
            return $senders[0] . ' with 1 person';
        } else {
            return $senders[0] . ' with ' . (count($senders) - 1) . ' people';
        }
    }


    /**
     * Static method to add gift to inventory
     */
    public static function addGift($userId, $giftId, $senderId = null, $quantity = 1, $context = 'livestream', $livestreamId = null)
    {
        // Get gift details for coin_value
        $gift = Gifts::find($giftId);
        $coinValue = $gift ? ($gift->coin_price * $quantity) : 0;

        // Check if same gift already exists for this user (regardless of sender)
        $existingGift = self::where([
            'user_id' => $userId,
            'gift_id' => $giftId,
            'is_converted' => false
        ])->first();

        $inventoryItem = null;

        if ($existingGift) {
            // Add to existing quantity and update received time
            $existingGift->quantity += $quantity;
            $existingGift->received_at = now(); // Update to latest received time

            // Add sender to the list if not already present
            $sendersList = $existingGift->received_from_user_id ?? [];
            if ($senderId && !in_array($senderId, $sendersList)) {
                $sendersList[] = $senderId;
                $existingGift->received_from_user_id = $sendersList;
            }

            $existingGift->save();
            $inventoryItem = $existingGift;
        } else {
            // Create new inventory item with sender as array
            $inventoryItem = self::create([
                'user_id' => $userId,
                'gift_id' => $giftId,
                'quantity' => $quantity,
                'received_from_user_id' => $senderId ? [$senderId] : [],
                'received_at' => now(),
            ]);
        }

        // Also insert into gift_transactions for analytics (normalized table)
        if ($senderId) {
            \DB::table('gift_transactions')->insert([
                'gift_inventory_id' => $inventoryItem->id,
                'sender_user_id' => $senderId,
                'receiver_user_id' => $userId,
                'gift_id' => $giftId,
                'quantity' => $quantity,
                'coin_value' => $coinValue,
                'context' => $context,
                'livestream_id' => $livestreamId,
                'gifted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $inventoryItem;
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