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
        'plan_type',
        'stripe_price_id',
        'status',
        'amount',
        'currency',
        'starts_at',
        'ends_at',
        'canceled_at',
        'metadata'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
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
        return $this->status === 'active' && 
               (!$this->ends_at || $this->ends_at->isFuture());
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
        return $query->where('status', 'active');
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

    // Static methods
    public static function getSubscriptionPlans()
    {
        return [
            'monthly' => [
                'name' => 'VIP Monthly',
                'price_id' => 'price_1RyQ8FG3TUB1R9FgbIYChZBL',
                'amount' => 10.00,
                'currency' => 'USD',
                'interval' => 'month'
            ],
            'yearly' => [
                'name' => 'VIP Yearly',
                'price_id' => 'price_1RyQ8WG3TUB1R9FgfgFVocKm',
                'amount' => 60.00,
                'currency' => 'USD',
                'interval' => 'year'
            ]
        ];
    }
}
