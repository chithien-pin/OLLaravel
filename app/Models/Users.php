<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    use HasFactory;

    public $table = "users";
    public $timestamps = false;
    
    protected $appends = ['role'];

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

    public function roles()
    {
        return $this->hasMany(UserRole::class, 'user_id', 'id');
    }

    public function activeRole()
    {
        return $this->hasOne(UserRole::class, 'user_id', 'id')
                    ->where('is_active', true)
                    ->where(function($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->latest('granted_at');
    }

    public function hasActiveRole()
    {
        return $this->activeRole()->exists();
    }

    public function getRoleAttribute()
    {
        $activeRole = $this->activeRole;
        return $activeRole ? [
            'role_type' => $activeRole->role_type,
            'role_type_id' => $activeRole->role_type_id,
            'role_display_name' => $activeRole->getRoleDisplayName(),
            'is_permanent' => $activeRole->isPermanent(),
            'expires_at' => $activeRole->expires_at,
            'granted_at' => $activeRole->granted_at
        ] : null;
    }
}
