<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // Expire VIP roles daily at midnight
        $schedule->command('roles:expire-vip')->daily();

        // Expire packages daily at midnight
        $schedule->command('packages:expire')->daily();

        // Reset daily swipe count at midnight
        $schedule->command('swipes:reset-daily')->daily();

        // Cleanup abandoned livestream sessions every 5 minutes
        // This removes stale Firebase documents from users who killed the app while streaming
        $schedule->command('livestream:cleanup-abandoned')->everyFiveMinutes();

        // 🔥 Periodic CDN Warmup - Maintain cache for popular/recent videos
        // Runs every 2 hours (configurable) to prevent Cloudflare cache expiration
        // Strategy: warm top 30 recent + top 30 popular videos globally (60 total)
        // This ensures consistent performance and eliminates cold start delays
        if (config('cloudflare.warmup_schedule_enabled', true)) {
            $interval = config('cloudflare.warmup_schedule_interval', 2);
            $schedule->command('videos:warmup-recent')
                ->cron("0 */{$interval} * * *") // Every N hours at :00 minutes
                ->withoutOverlapping() // Prevent concurrent runs
                ->runInBackground() // Don't block other scheduled tasks
                ->onFailure(function () {
                    \Log::error('🔥 [PERIODIC_WARMUP] Scheduled warmup failed to execute');
                });
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
