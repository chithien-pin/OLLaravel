<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Users;
use App\Models\UserRole;
use Carbon\Carbon;

class DefaultUserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get all users who don't have any roles assigned
        $usersWithoutRoles = Users::whereDoesntHave('roles')->get();
        
        foreach ($usersWithoutRoles as $user) {
            // Assign default 'normal' role to all users without roles
            UserRole::create([
                'user_id' => $user->id,
                'role_type' => 'normal',
                'granted_at' => $user->created_at ?? Carbon::now(),
                'expires_at' => null, // Normal role never expires
                'granted_by_admin_id' => null, // System assigned
                'is_active' => true
            ]);
        }
        
        $this->command->info('Default normal roles assigned to ' . $usersWithoutRoles->count() . ' users');
    }
}