<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserRole extends Model
{
    use HasFactory;

    protected $table = 'user_roles';

    protected $fillable = [
        'user_id',
        'role_type',
        'granted_at',
        'expires_at',
        'granted_by_admin_id',
        'is_active'
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    public function grantedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'granted_by_admin_id', 'user_id');
    }

    // Helper methods
    public function isExpired()
    {
        if ($this->role_type === 'normal') {
            return false; // Normal role never expires
        }
        
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive()
    {
        return $this->is_active && !$this->isExpired();
    }

    public function getDaysRemaining()
    {
        if ($this->role_type === 'normal' || !$this->expires_at) {
            return null; // Normal role has no expiry
        }

        if ($this->isExpired()) {
            return 0;
        }

        return Carbon::now()->diffInDays($this->expires_at, false);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('role_type', 'normal')
              ->orWhere('expires_at', '>', Carbon::now())
              ->orWhereNull('expires_at');
        });
    }

    public function scopeVip($query)
    {
        return $query->where('role_type', 'vip');
    }

    public function scopeNormal($query)
    {
        return $query->where('role_type', 'normal');
    }
}