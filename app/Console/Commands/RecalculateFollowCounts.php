<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateFollowCounts extends Command
{
    protected $signature = 'fix:follow-counts';
    protected $description = 'Recalculate followers and following counts for all active users';

    public function handle()
    {
        // Get all active user IDs
        $validUserIds = DB::table('users')
            ->whereNull('deleted_at')
            ->where('is_block', 0)
            ->pluck('id')
            ->toArray();

        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->select('id', 'fullname', 'followers', 'following')
            ->get();

        $this->info("Processing {$users->count()} users...");
        $fixed = 0;

        foreach ($users as $user) {
            // Count actual followers (people who follow this user, only valid users)
            $actualFollowers = DB::table('following_lists')
                ->where('user_id', $user->id)
                ->whereIn('my_user_id', $validUserIds)
                ->count();

            // Count actual following (people this user follows, only valid users)
            $actualFollowing = DB::table('following_lists')
                ->where('my_user_id', $user->id)
                ->whereIn('user_id', $validUserIds)
                ->count();

            if ($user->followers != $actualFollowers || $user->following != $actualFollowing) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'followers' => $actualFollowers,
                        'following' => $actualFollowing,
                    ]);

                $this->line("  Fixed #{$user->id} {$user->fullname}: followers {$user->followers}→{$actualFollowers}, following {$user->following}→{$actualFollowing}");
                $fixed++;
            }

            // Sleep 50ms between each user to avoid overloading
            usleep(50000);
        }

        $this->info("Done! Fixed {$fixed}/{$users->count()} users.");
    }
}
