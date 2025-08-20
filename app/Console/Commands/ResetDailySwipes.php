<?php

namespace App\Console\Commands;

use App\Models\Users;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetDailySwipes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swipes:reset-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset daily swipe count for all users at midnight';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting daily swipe reset process...');

        try {
            // Reset daily_swipes to 0 for all users
            $affectedUsers = Users::where('daily_swipes', '>', 0)
                                 ->update([
                                     'daily_swipes' => 0,
                                     'last_swipe_date' => now()->toDateString()
                                 ]);

            $this->info("Successfully reset daily swipes for {$affectedUsers} users.");
            
            Log::info("Daily swipe reset completed", [
                'affected_users' => $affectedUsers,
                'executed_at' => now()->toDateTimeString()
            ]);

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to reset daily swipes: " . $e->getMessage());
            
            Log::error("Daily swipe reset failed", [
                'error' => $e->getMessage(),
                'executed_at' => now()->toDateTimeString()
            ]);

            return Command::FAILURE;
        }
    }
}