<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'payment_intent_id',
        'plan_type',
        'stripe_price_id',
        'status',
        'amount',
        'currency',
        'starts_at',
        'ends_at',
        'canceled_at',
        'webhook_confirmed_at',
        'payment_intent_verified',
        'metadata'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'webhook_confirmed_at' => 'datetime',
        'payment_intent_verified' => 'boolean',
        'metadata' => 'array',
        'amount' => 'decimal:2'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    public function userRoles()
    {
        return $this->hasMany(UserRole::class, 'subscription_id');
    }

    // Helper methods
    public function isActive()
    {
        return ($this->status === 'active' || $this->status === 'pending_webhook') && 
               (!$this->ends_at || $this->ends_at->isFuture());
    }

    public function isPendingWebhook()
    {
        return $this->status === 'pending_webhook';
    }

    public function isWebhookConfirmed()
    {
        return $this->webhook_confirmed_at !== null;
    }

    public function isPaymentIntentVerified()
    {
        return $this->payment_intent_verified === true;
    }

    public function isExpired()
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function getDaysRemaining()
    {
        if (!$this->ends_at) {
            return null;
        }

        if ($this->isExpired()) {
            return 0;
        }

        return Carbon::now()->diffInDays($this->ends_at, false);
    }

    public function getPlanDisplayName()
    {
        return $this->plan_type === 'monthly' ? 'VIP Monthly' : 'VIP Yearly';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'pending_webhook']);
    }

    public function scopePendingWebhook($query)
    {
        return $query->where('status', 'pending_webhook');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('ends_at')
              ->orWhere('ends_at', '>', Carbon::now());
        });
    }

    // Static methods - Now reads from subscription_packs table
    public static function getSubscriptionPlans()
    {
        $packs = SubscriptionPacks::getAllPacks();
        $plans = [];

        foreach ($packs as $pack) {
            $plans[$pack->plan_type] = [
                'name' => $pack->getDisplayName(),
                'price_id' => $pack->android_product_id, // Stripe Price ID stored in android_product_id
                'amount' => (float) $pack->amount,
                'currency' => $pack->currency ?? 'USD',
                'interval' => $pack->interval_type ?? 'month',
                'first_time_only' => $pack->first_time_only ?? false,
                'type' => $pack->type ?? 'role'
            ];
        }

        return $plans;
    }

    // Check if user is eligible for starter plan (first-time subscriber)
    public static function isEligibleForStarterPlan($userId)
    {
        return !self::where('user_id', $userId)->exists();
    }
}
