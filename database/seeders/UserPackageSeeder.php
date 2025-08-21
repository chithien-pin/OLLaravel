<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserPackage;
use App\Models\Users;
use Carbon\Carbon;

class UserPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get some existing users for testing
        $users = Users::take(10)->get();
        
        if ($users->count() == 0) {
            $this->command->info('No users found. Please seed users first.');
            return;
        }

        $packageTypes = ['millionaire', 'billionaire', 'celebrity'];
        $count = 0;

        foreach ($users as $index => $user) {
            // Assign different packages to different users
            $packageType = $packageTypes[$index % 3];
            
            // Calculate expiry date
            $expiresAt = null;
            if ($packageType === 'millionaire' || $packageType === 'billionaire') {
                $expiresAt = Carbon::now()->addYear();
            }
            // Celebrity is permanent

            UserPackage::create([
                'user_id' => $user->id,
                'package_type' => $packageType,
                'granted_at' => Carbon::now(),
                'expires_at' => $expiresAt,
                'granted_by_admin_id' => 1, // Assuming admin ID 1 exists
                'is_active' => true
            ]);
            
            $count++;
            $this->command->info("Assigned {$packageType} package to user ID: {$user->id}");
        }

        // Create one expired package for testing expiration
        if ($users->count() > 0) {
            $expiredPackage = UserPackage::create([
                'user_id' => $users->first()->id,
                'package_type' => 'millionaire',
                'granted_at' => Carbon::now()->subMonths(13), // 13 months ago
                'expires_at' => Carbon::now()->subMonth(), // Expired 1 month ago
                'granted_by_admin_id' => 1,
                'is_active' => true // Still active to test expiration command
            ]);
            
            $count++;
            $this->command->info("Created expired millionaire package for testing (user ID: {$users->first()->id})");
        }

        $this->command->info("Created {$count} package records");
    }
}
