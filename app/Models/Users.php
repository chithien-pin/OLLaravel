<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    use HasFactory;

    public $table = "users";
    public $timestamps = false;

    public function images()
    {
        return $this->hasMany(Images::class, 'user_id', 'id');
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class, 'user_id', 'id');
    }

    public function interests()
    {
        return $this->hasMany(Interest::class, 'id', 'interests');
    }

    function liveApplications()
    {
        return $this->hasOne(LiveApplications::class, 'user_id', 'id');
    }


    
    function verifyRequest()
    {
        return $this->hasOne(VerifyRequest::class, 'user_id', 'id');
    }

    function liveHistory()
    {
        return $this->hasMany(LiveHistory::class, 'user_id', 'id');
    }

    function redeemRequests()
    {
        return $this->hasMany(RedeemRequest::class, 'user_id', 'id');
    }

    public function stories()
    {
        return $this->hasMany(Story::class, 'user_id', 'id');
    }

    // User Role relationships and methods
    public function roles()
    {
        return $this->hasMany(UserRole::class, 'user_id', 'id');
    }

    public function activeRole()
    {
        return $this->hasOne(UserRole::class, 'user_id', 'id')
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->where('role_type', 'normal')
                              ->orWhere('expires_at', '>', now())
                              ->orWhereNull('expires_at');
                    })
                    ->latest('granted_at');
    }

    public function currentRole()
    {
        $activeRole = $this->activeRole;
        return $activeRole ? $activeRole : $this->getDefaultRole();
    }

    public function getCurrentRoleType()
    {
        $currentRole = $this->currentRole();
        return $currentRole ? $currentRole->role_type : 'normal';
    }

    public function isVip()
    {
        $currentRole = $this->currentRole();
        return $currentRole && $currentRole->role_type === 'vip' && $currentRole->isActive();
    }

    public function isNormal()
    {
        return !$this->isVip();
    }

    public function getRoleExpiryDate()
    {
        $currentRole = $this->currentRole();
        if ($currentRole && $currentRole->role_type === 'vip') {
            return $currentRole->expires_at;
        }
        return null;
    }

    public function getDaysRemainingForVip()
    {
        $currentRole = $this->currentRole();
        if ($currentRole && $currentRole->role_type === 'vip') {
            return $currentRole->getDaysRemaining();
        }
        return null;
    }

    private function getDefaultRole()
    {
        // Return a mock normal role object for users without any role assigned
        $defaultRole = new UserRole();
        $defaultRole->role_type = 'normal';
        $defaultRole->granted_at = $this->created_at ?? now();
        $defaultRole->expires_at = null;
        $defaultRole->is_active = true;
        return $defaultRole;
    }

    // Method to assign role to user
    public function assignRole($roleType, $duration = null, $adminId = null)
    {
        // Deactivate existing roles
        $this->roles()->update(['is_active' => false]);

        // Calculate expiry date for VIP roles
        $expiresAt = null;
        if ($roleType === 'vip' && $duration) {
            if ($duration === '1_year') {
                $expiresAt = now()->addMonths(12);
            } elseif ($duration === '1_month') {
                $expiresAt = now()->addMonths(1);
            } elseif ($duration === '20_seconds') {
                $expiresAt = now()->addSeconds(20);
            }
        }

        // Create new role
        return $this->roles()->create([
            'role_type' => $roleType,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'granted_by_admin_id' => $adminId,
            'is_active' => true
        ]);
    }

    // Method to revoke role
    public function revokeRole()
    {
        // Deactivate current role and assign normal role
        $this->roles()->update(['is_active' => false]);
        
        return $this->assignRole('normal');
    }
}
