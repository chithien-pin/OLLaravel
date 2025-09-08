<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPacks extends Model
{
    use HasFactory;

    protected $table = 'subscription_packs';

    protected $fillable = [
        'plan_type',
        'amount',
        'currency',
        'ios_product_id',
        'android_product_id',
        'interval_type',
        'type',
        'first_time_only'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'first_time_only' => 'boolean',
    ];

    /**
     * Get all subscription packs
     */
    public static function getAllPacks()
    {
        return self::orderBy('amount')->get();
    }

    /**
     * Get subscription pack by plan type
     */
    public static function getByPlanType($planType)
    {
        return self::where('plan_type', $planType)->first();
    }

    /**
     * Get iOS product IDs
     */
    public static function getIOSProductIds()
    {
        return self::whereNotNull('ios_product_id')
                   ->pluck('ios_product_id', 'plan_type')
                   ->toArray();
    }

    /**
     * Find pack by iOS product ID
     */
    public static function findByIOSProductId($productId)
    {
        return self::where('ios_product_id', $productId)->first();
    }

    /**
     * Get VIP role packs (starter, monthly, yearly)
     */
    public static function getRolePacks()
    {
        return self::where('type', 'role')->orderBy('amount')->get();
    }

    /**
     * Get premium packages (millionaire, billionaire)
     */
    public static function getPackagePacks()
    {
        return self::where('type', 'package')->orderBy('amount')->get();
    }

    /**
     * Check if plan type is eligible for first time users
     */
    public function isFirstTimeOnly()
    {
        return $this->first_time_only;
    }

    /**
     * Get display name for the plan
     */
    public function getDisplayName()
    {
        return match($this->plan_type) {
            'starter' => 'VIP Starter',
            'monthly' => 'VIP Monthly',
            'yearly' => 'VIP Yearly',
            'millionaire' => 'Millionaire Package',
            'billionaire' => 'Billionaire Package',
            default => ucfirst($this->plan_type)
        };
    }

    /**
     * Get duration description
     */
    public function getDuration()
    {
        return match($this->interval_type) {
            'month' => '1 Month',
            'year' => '1 Year',
            default => $this->interval_type
        };
    }

    /**
     * Convert to array for API response
     */
    public function toApiArray()
    {
        return [
            'plan_type' => $this->plan_type,
            'name' => $this->getDisplayName(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'ios_product_id' => $this->ios_product_id,
            'android_product_id' => $this->android_product_id,
            'interval_type' => $this->interval_type,
            'duration' => $this->getDuration(),
            'type' => $this->type,
            'first_time_only' => $this->first_time_only
        ];
    }
}