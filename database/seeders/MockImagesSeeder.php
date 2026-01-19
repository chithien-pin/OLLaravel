<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MockImagesSeeder extends Seeder
{
    public function run()
    {
        // Available images
        $images = [
            'uploads/E0zTAf08OiIB90diOvRsIDsSl4szQepptD5bsnzM.jpg',
            'uploads/LYX8MAFOi540FQqUlvkeEt4Pmz9EVjFQgEMyUhS0.jpg',
            'uploads/aKcAPbGcoKu67nLdyn7ZieOImnLkH7vb6ETUHZcz.jpg',
            'uploads/d3pSFMh2F5nTqF8fmF8GHXfDDQTYoMgJ5KD8oHLh.jpg',
            'uploads/lhq9qv88uP5lXNxF4yUselT0FaarwdVZ9v91ERC8.jpg',
        ];

        // Get all fake user IDs except 286
        $userIds = DB::table('users')
            ->where('is_fake', 1)
            ->where('id', '!=', 286)
            ->pluck('id')
            ->toArray();

        $this->command->info("Found " . count($userIds) . " fake users (excluding ID 286)");

        $insertData = [];

        foreach ($userIds as $userId) {
            // 1 random image per user
            $insertData[] = [
                'user_id' => $userId,
                'image' => $images[array_rand($images)],
            ];
        }

        $this->command->info("Inserting " . count($insertData) . " image records...");

        // Insert in chunks
        foreach (array_chunk($insertData, 500) as $chunk) {
            DB::table('images')->insert($chunk);
        }

        $this->command->info("✅ Created " . count($insertData) . " image records for " . count($userIds) . " users!");
    }
}
