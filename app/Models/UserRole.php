<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_type',
        'role_type_id', 
        'granted_at',
        'expires_at',
        'is_active',
        'granted_by_admin_id'
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    // Constants for role types
    const ROLE_VIP = 'VIP';
    const ROLE_MILLIONAIRE = 'Millionaire';
    const ROLE_BILLIONAIRE = 'Billionaire';
    const ROLE_CELEBRITY = 'Celebrity';

    const ROLE_TYPE_IDS = [
        self::ROLE_VIP => 1,
        self::ROLE_MILLIONAIRE => 2,
        self::ROLE_BILLIONAIRE => 3,
        self::ROLE_CELEBRITY => 4
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(Admin::class, 'granted_by_admin_id', 'user_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
                     ->where('expires_at', '<=', now());
    }

    // Helper methods
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isPermanent()
    {
        return is_null($this->expires_at);
    }

    public function getRoleDisplayName()
    {
        $roleNames = [
            self::ROLE_VIP => 'VIP',
            self::ROLE_MILLIONAIRE => 'Millionaire',
            self::ROLE_BILLIONAIRE => 'Billionaire', 
            self::ROLE_CELEBRITY => 'Celebrity'
        ];
        
        return $roleNames[$this->role_type] ?? $this->role_type;
    }

    public static function createRole($userId, $roleType, $duration = null, $adminId = 1)
    {
        // Deactivate existing active roles for this user
        self::where('user_id', $userId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Calculate expiry date based on role type and duration
        $expiresAt = null;
        if ($roleType !== self::ROLE_CELEBRITY) {
            if ($duration === '1_month') {
                $expiresAt = now()->addMonth();
            } elseif ($duration === '1_year') {
                $expiresAt = now()->addYear();
            }
        }

        return self::create([
            'user_id' => $userId,
            'role_type' => $roleType,
            'role_type_id' => self::ROLE_TYPE_IDS[$roleType],
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'is_active' => true,
            'granted_by_admin_id' => $adminId
        ]);
    }
}
