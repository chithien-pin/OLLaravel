<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;

class Users extends Model
{
    use HasFactory, Billable;

    public $table = "users";
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'daily_swipes',
        'last_swipe_date',
    ];

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

    /**
     * Likes given by this user (my_user_id)
     */
    public function likesGiven()
    {
        return $this->hasMany(LikedProfile::class, 'my_user_id', 'id');
    }
    
    /**
     * Likes received by this user (user_id)
     */
    public function likesReceived()
    {
        return $this->hasMany(LikedProfile::class, 'user_id', 'id');
    }

    // Subscription relationships
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'user_id', 'id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class, 'user_id', 'id')
                    ->active()
                    ->notExpired()
                    ->latest('created_at');
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
        // Deactivate current role (consistent with revokePackage logic)
        return $this->roles()->update(['is_active' => false]);
    }

    // User Package relationships and methods
    public function packages()
    {
        return $this->hasMany(UserPackage::class, 'user_id', 'id');
    }

    public function activePackage()
    {
        return $this->hasOne(UserPackage::class, 'user_id', 'id')
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->where('package_type', 'celebrity')
                              ->orWhere('expires_at', '>', now())
                              ->orWhereNull('expires_at');
                    })
                    ->latest('granted_at');
    }

    public function currentPackage()
    {
        return $this->activePackage;
    }

    public function getCurrentPackageType()
    {
        $currentPackage = $this->currentPackage();
        return $currentPackage ? $currentPackage->package_type : null;
    }

    public function hasPackage()
    {
        $currentPackage = $this->currentPackage();
        return $currentPackage && $currentPackage->isActive();
    }

    public function isMillionaire()
    {
        $currentPackage = $this->currentPackage();
        return $currentPackage && $currentPackage->package_type === 'millionaire' && $currentPackage->isActive();
    }

    public function isBillionaire()
    {
        $currentPackage = $this->currentPackage();
        return $currentPackage && $currentPackage->package_type === 'billionaire' && $currentPackage->isActive();
    }

    public function isCelebrity()
    {
        $currentPackage = $this->currentPackage();
        return $currentPackage && $currentPackage->package_type === 'celebrity' && $currentPackage->isActive();
    }

    public function getPackageExpiryDate()
    {
        $currentPackage = $this->currentPackage();
        if ($currentPackage && $currentPackage->package_type !== 'celebrity') {
            return $currentPackage->expires_at;
        }
        return null;
    }

    public function getDaysRemainingForPackage()
    {
        $currentPackage = $this->currentPackage();
        if ($currentPackage) {
            return $currentPackage->getDaysRemaining();
        }
        return null;
    }

    public function getPackageDisplayName()
    {
        $currentPackage = $this->currentPackage();
        return $currentPackage ? $currentPackage->getPackageDisplayName() : null;
    }

    public function getPackageBadgeColor()
    {
        $currentPackage = $this->currentPackage();
        return $currentPackage ? $currentPackage->getPackageBadgeColor() : null;
    }

    // Method to assign package to user
    public function assignPackage($packageType, $adminId = null)
    {
        // Deactivate existing packages
        $this->packages()->update(['is_active' => false]);

        // Calculate expiry date based on package type
        $expiresAt = null;
        if ($packageType === 'millionaire' || $packageType === 'billionaire') {
            $expiresAt = now()->addYear(); // 1 year duration
        }
        // Celebrity package is permanent (no expiry date)

        // Create new package
        return $this->packages()->create([
            'package_type' => $packageType,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'granted_by_admin_id' => $adminId,
            'is_active' => true
        ]);
    }

    // Method to revoke package
    public function revokePackage()
    {
        // Deactivate current package
        return $this->packages()->update(['is_active' => false]);
    }

    // Swipe Limiting Methods
    
    /**
     * Check if user can swipe today based on their role and daily limit
     *
     * @return bool
     */
    public function canSwipeToday()
    {
        // VIP users have unlimited swipes
        if ($this->isVip()) {
            return true;
        }

        // Check if we need to reset daily swipes for a new day
        $this->resetDailySwipesIfNeeded();

        // Get app settings directly from database
        $appData = AppData::first();
        $swipeLimit = $appData ? $appData->getSwipeLimit() : 50;

        return $this->daily_swipes < $swipeLimit;
    }

    /**
     * Increment the daily swipe count
     *
     * @return bool
     */
    public function incrementSwipeCount()
    {
        // Use database transaction to prevent race conditions
        return \DB::transaction(function () {
            // Reset daily swipes if it's a new day
            $this->resetDailySwipesIfNeeded();

            // Use atomic increment to prevent race conditions
            return $this->increment('daily_swipes', 1, [
                'last_swipe_date' => now()->toDateString()
            ]);
        });
    }

    /**
     * Get remaining swipes for today
     *
     * @return int
     */
    public function getRemainingSwipes()
    {
        // VIP users have unlimited swipes
        if ($this->isVip()) {
            return -1; // -1 indicates unlimited
        }

        // Check if we need to reset daily swipes for a new day
        $this->resetDailySwipesIfNeeded();

        // Get app settings directly from database
        $appData = AppData::first();
        $swipeLimit = $appData ? $appData->getSwipeLimit() : 50;

        return max(0, $swipeLimit - $this->daily_swipes);
    }

    /**
     * Reset daily swipes if it's a new day
     *
     * @return void
     */
    public function resetDailySwipesIfNeeded()
    {
        $today = now()->toDateString();
        
        if ($this->last_swipe_date !== $today) {
            // Use atomic update to prevent race conditions
            $this->update([
                'daily_swipes' => 0,
                'last_swipe_date' => $today
            ]);
        }
    }

    /**
     * Manual reset daily swipes (for admin or cronjob)
     *
     * @return bool
     */
    public function resetDailySwipes()
    {
        $this->daily_swipes = 0;
        $this->last_swipe_date = now()->toDateString();
        return $this->save();
    }
}
