<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gifts extends Model
{
    use HasFactory;
    public $table = "gifts";
    
    protected $fillable = [
        'name',
        'image',
        'coin_price',
        'category',
        'rarity',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all inventory items for this gift
     */
    public function inventoryItems()
    {
        return $this->hasMany(UserGiftInventory::class, 'gift_id');
    }

    /**
     * Scope to get only active gifts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get gifts by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get gifts by rarity
     */
    public function scopeByRarity($query, $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    /**
     * Get formatted coin price
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->coin_price) . ' coins';
    }

    /**
     * Get rarity color for UI
     */
    public function getRarityColorAttribute()
    {
        $colors = [
            'common' => '#9E9E9E',
            'rare' => '#2196F3', 
            'epic' => '#9C27B0',
            'legendary' => '#FF9800'
        ];
        
        return $colors[$this->rarity] ?? $colors['common'];
    }
}
