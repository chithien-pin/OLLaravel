<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserRole;
use Carbon\Carbon;

class ExpireVipRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'roles:expire-vip';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire VIP roles that have reached their expiry date and assign normal role';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $expiredRoles = UserRole::where('role_type', 'vip')
                                ->where('is_active', true)
                                ->where('expires_at', '<=', Carbon::now())
                                ->get();

        $expiredCount = 0;
        
        foreach ($expiredRoles as $role) {
            // Deactivate expired VIP role
            $role->update(['is_active' => false]);
            
            // Create new normal role
            UserRole::create([
                'user_id' => $role->user_id,
                'role_type' => 'normal',
                'granted_at' => Carbon::now(),
                'expires_at' => null,
                'granted_by_admin_id' => null, // System assigned
                'is_active' => true
            ]);
            
            $expiredCount++;
        }

        $this->info("Expired {$expiredCount} VIP roles and assigned normal roles");
        
        return 0;
    }
}