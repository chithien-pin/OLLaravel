<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserPackage;
use Carbon\Carbon;

class ExpirePackages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire packages that have reached their expiry date (excludes permanent celebrity packages)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Find expired packages (excludes celebrity which is permanent)
        $expiredPackages = UserPackage::where('package_type', '!=', 'celebrity')
                                     ->where('is_active', true)
                                     ->where('expires_at', '<=', Carbon::now())
                                     ->get();

        $expiredCount = 0;
        
        foreach ($expiredPackages as $package) {
            // Deactivate expired package
            $package->update(['is_active' => false]);
            $expiredCount++;
            
            $this->info("Expired {$package->package_type} package for user ID: {$package->user_id}");
        }

        $this->info("Total expired packages: {$expiredCount}");
        
        return 0;
    }
}
