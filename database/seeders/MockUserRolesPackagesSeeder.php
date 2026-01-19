<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MockUserRolesPackagesSeeder extends Seeder
{
    public function run()
    {
        $now = now();

        // Get all fake user IDs
        $userIds = DB::table('users')
            ->where('is_fake', 1)
            ->pluck('id')
            ->toArray();

        $this->command->info("Found " . count($userIds) . " fake users");

        // Shuffle to randomize
        shuffle($userIds);

        // ==================== VIP ROLES ====================
        // 10% of users get VIP role
        $vipCount = (int)(count($userIds) * 0.10);
        $vipUserIds = array_slice($userIds, 0, $vipCount);

        $this->command->info("Upgrading {$vipCount} users to VIP...");

        foreach ($vipUserIds as $userId) {
            // Deactivate current 'normal' role
            DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role_type', 'normal')
                ->update(['is_active' => false]);

            // Random expiry: 70% active (future expiry), 30% expired
            $isExpired = mt_rand(1, 100) <= 30;

            if ($isExpired) {
                // Expired VIP (1-30 days ago)
                $grantedAt = $now->copy()->subDays(mt_rand(30, 90));
                $expiresAt = $now->copy()->subDays(mt_rand(1, 29));
                $isActive = false;
            } else {
                // Active VIP (expires in 1-365 days)
                $grantedAt = $now->copy()->subDays(mt_rand(1, 60));
                $expiresAt = $now->copy()->addDays(mt_rand(1, 365));
                $isActive = true;
            }

            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role_type' => 'vip',
                'granted_at' => $grantedAt,
                'expires_at' => $expiresAt,
                'granted_by_admin_id' => null,
                'subscription_id' => null,
                'is_active' => $isActive,
                'created_at' => $grantedAt,
                'updated_at' => $now,
            ]);
        }

        // ==================== USER PACKAGES ====================
        // 8% of users get a package
        $packageCount = (int)(count($userIds) * 0.08);

        // Shuffle again and pick different users (can overlap with VIP)
        shuffle($userIds);
        $packageUserIds = array_slice($userIds, 0, $packageCount);

        $packageTypes = ['millionaire', 'billionaire', 'celebrity'];
        // Distribution: 50% millionaire, 35% billionaire, 15% celebrity
        $packageWeights = [
            'millionaire' => 50,
            'billionaire' => 35,
            'celebrity' => 15,
        ];
        $packagePool = [];
        foreach ($packageWeights as $type => $weight) {
            $packagePool = array_merge($packagePool, array_fill(0, $weight, $type));
        }

        $this->command->info("Creating {$packageCount} user packages...");

        $packageData = [];
        foreach ($packageUserIds as $userId) {
            $packageType = $packagePool[array_rand($packagePool)];

            // Random expiry: 80% active, 20% expired
            $isExpired = mt_rand(1, 100) <= 20;

            if ($isExpired) {
                $grantedAt = $now->copy()->subDays(mt_rand(60, 180));
                $expiresAt = $now->copy()->subDays(mt_rand(1, 59));
                $isActive = false;
            } else {
                $grantedAt = $now->copy()->subDays(mt_rand(1, 90));
                $expiresAt = $now->copy()->addDays(mt_rand(30, 365));
                $isActive = true;
            }

            $packageData[] = [
                'user_id' => $userId,
                'package_type' => $packageType,
                'granted_at' => $grantedAt,
                'expires_at' => $expiresAt,
                'granted_by_admin_id' => null,
                'is_active' => $isActive,
                'created_at' => $grantedAt,
                'updated_at' => $now,
            ];
        }

        // Insert packages
        foreach (array_chunk($packageData, 100) as $chunk) {
            DB::table('user_packages')->insert($chunk);
        }

        // ==================== SUMMARY ====================
        $activeVip = DB::table('user_roles')
            ->whereIn('user_id', $userIds)
            ->where('role_type', 'vip')
            ->where('is_active', true)
            ->count();

        $activePackages = DB::table('user_packages')
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->count();

        $this->command->info("✅ Done!");
        $this->command->info("📊 Summary:");
        $this->command->info("   - Total VIP roles: {$vipCount} ({$activeVip} active)");
        $this->command->info("   - Total packages: {$packageCount} ({$activePackages} active)");
    }
}
