<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiamondPacks extends Model
{
    // Diamond Packs
    use HasFactory;
    public $table = "diamond_packs";
    public $timestamps = false;

    protected $fillable = [
        'amount',
        'price',
        'ios_product_id',
        'android_product_id',
        'image'
    ];

    /**
     * Get all diamond packs ordered by amount
     */
    public static function getAllPacks()
    {
        return self::orderBy('amount')->get();
    }

    /**
     * Find pack by amount
     */
    public static function findByAmount($amount)
    {
        return self::where('amount', $amount)->first();
    }

    /**
     * Find pack by Stripe Price ID (android_product_id)
     */
    public static function findByStripePriceId($priceId)
    {
        return self::where('android_product_id', $priceId)->first();
    }
}
