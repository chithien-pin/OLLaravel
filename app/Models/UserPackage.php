<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserPackage extends Model
{
    use HasFactory;

    protected $table = 'user_packages';

    protected $fillable = [
        'user_id',
        'package_type',
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
        if ($this->package_type === 'celebrity') {
            return false; // Celebrity package is permanent
        }
        
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive()
    {
        return $this->is_active && !$this->isExpired();
    }

    public function getDaysRemaining()
    {
        if ($this->package_type === 'celebrity' || !$this->expires_at) {
            return null; // Permanent package has no expiry
        }

        if ($this->isExpired()) {
            return 0;
        }

        return Carbon::now()->diffInDays($this->expires_at, false);
    }

    public function isPermanent()
    {
        return $this->package_type === 'celebrity';
    }

    public function getPackageDisplayName()
    {
        return ucfirst($this->package_type);
    }

    public function getPackageBadgeColor()
    {
        $colors = [
            'millionaire' => '#10B981', // Green
            'billionaire' => '#3B82F6',  // Blue
            'celebrity' => '#8B5CF6'     // Purple
        ];

        return $colors[$this->package_type] ?? '#6B7280';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('package_type', 'celebrity')
              ->orWhere('expires_at', '>', Carbon::now())
              ->orWhereNull('expires_at');
        });
    }

    public function scopeMillionaire($query)
    {
        return $query->where('package_type', 'millionaire');
    }

    public function scopeBillionaire($query)
    {
        return $query->where('package_type', 'billionaire');
    }

    public function scopeCelebrity($query)
    {
        return $query->where('package_type', 'celebrity');
    }

    public function scopeExpired($query)
    {
        return $query->where('package_type', '!=', 'celebrity')
                    ->where('expires_at', '<=', Carbon::now());
    }
}
