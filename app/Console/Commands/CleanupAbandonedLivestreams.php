<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Users;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup Abandoned Livestream Sessions
 *
 * This command runs periodically to clean up livestream sessions that were
 * abandoned when users killed the app without properly ending the stream.
 *
 * It checks Firebase Firestore for active livestream documents and removes
 * stale sessions that have been inactive for more than 5 minutes.
 */
class CleanupAbandonedLivestreams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'livestream:cleanup-abandoned
                            {--timeout=5 : Minutes of inactivity before cleanup (default: 5)}
                            {--dry-run : Run without actually deleting anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup abandoned livestream sessions from Firebase that are inactive for more than specified minutes';

    /**
     * Firebase service instance
     *
     * @var FirebaseService
     */
    protected $firebaseService;

    /**
     * Timeout in minutes before considering a livestream abandoned
     *
     * @var int
     */
    protected $timeoutMinutes;

    /**
     * Dry run mode - don't actually delete anything
     *
     * @var bool
     */
    protected $dryRun;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->timeoutMinutes = (int) $this->option('timeout');
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->info("🔍 DRY RUN MODE - No changes will be made");
        }

        $this->info("🧹 Starting cleanup of abandoned livestream sessions...");
        $this->info("⏱️  Timeout threshold: {$this->timeoutMinutes} minutes of inactivity");
        $this->line("");

        // Get all users who are marked as live in MySQL
        $liveUsers = Users::where('is_live_now', 1)->get();

        if ($liveUsers->isEmpty()) {
            $this->info("✅ No users are currently marked as live in database");
            return 0;
        }

        $this->info("📊 Found {$liveUsers->count()} user(s) marked as live in MySQL");
        $this->line("");

        $cleanedCount = 0;
        $activeCount = 0;
        $errorCount = 0;

        foreach ($liveUsers as $user) {
            $userId = $user->id;
            $userName = $user->fullname ?? "User {$userId}";

            $this->line("🔍 Checking user: {$userName} (ID: {$userId})");

            try {
                // Check if livestream document exists in Firebase
                $exists = $this->firebaseService->documentExists('liveHostList', $userId);

                if (!$exists) {
                    // Document doesn't exist but MySQL says user is live
                    // This is a database inconsistency - fix it
                    $this->warn("  ⚠️  Livestream document not found in Firebase, but MySQL is_live_now=1");

                    if (!$this->dryRun) {
                        $user->is_live_now = 0;
                        $user->save();
                        $this->info("  ✅ Updated MySQL: is_live_now = 0");
                    } else {
                        $this->comment("  [DRY RUN] Would update MySQL: is_live_now = 0");
                    }

                    $cleanedCount++;
                    continue;
                }

                // Document exists - check last update time
                $updateTime = $this->firebaseService->getDocumentUpdateTime('liveHostList', $userId);

                if ($updateTime) {
                    $lastUpdate = Carbon::parse($updateTime);
                    $minutesInactive = $lastUpdate->diffInMinutes(Carbon::now());

                    $this->line("  📅 Last activity: {$lastUpdate->toDateTimeString()} ({$minutesInactive} min ago)");

                    if ($minutesInactive >= $this->timeoutMinutes) {
                        // Livestream is abandoned - cleanup
                        $this->warn("  🚨 ABANDONED: Inactive for {$minutesInactive} minutes (threshold: {$this->timeoutMinutes})");

                        if (!$this->dryRun) {
                            $this->cleanupLivestream($user);
                            $this->info("  ✅ Cleaned up successfully");
                        } else {
                            $this->comment("  [DRY RUN] Would cleanup this livestream");
                        }

                        $cleanedCount++;
                    } else {
                        // Still active
                        $this->info("  ✅ ACTIVE: Still within activity threshold");
                        $activeCount++;
                    }
                } else {
                    // Couldn't get update time - assume abandoned for safety
                    $this->warn("  ⚠️  Could not determine last activity time - considering abandoned");

                    if (!$this->dryRun) {
                        $this->cleanupLivestream($user);
                        $this->info("  ✅ Cleaned up successfully");
                    } else {
                        $this->comment("  [DRY RUN] Would cleanup this livestream");
                    }

                    $cleanedCount++;
                }

            } catch (\Exception $e) {
                $this->error("  💥 Error processing user {$userId}: " . $e->getMessage());
                Log::error("Livestream cleanup error for user {$userId}: " . $e->getMessage());
                $errorCount++;
            }

            $this->line("");
        }

        // Summary
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 CLEANUP SUMMARY:");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("  👥 Total users checked: {$liveUsers->count()}");
        $this->line("  🧹 Abandoned/Cleaned up: {$cleanedCount}");
        $this->line("  ✅ Active livestreams: {$activeCount}");
        if ($errorCount > 0) {
            $this->line("  ❌ Errors: {$errorCount}");
        }
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        if ($this->dryRun) {
            $this->comment("🔍 DRY RUN COMPLETE - No actual changes were made");
        }

        return 0;
    }

    /**
     * Cleanup a single livestream session
     *
     * @param Users $user
     * @return void
     */
    protected function cleanupLivestream(Users $user)
    {
        $userId = $user->id;

        // 1. Delete comments subcollection
        $deletedComments = $this->firebaseService->deleteSubcollection('liveHostList', $userId, 'comments');
        if ($deletedComments > 0) {
            $this->line("    🗑️  Deleted {$deletedComments} comment(s)");
        }

        // 2. Delete main livestream document
        $deleted = $this->firebaseService->deleteDocument('liveHostList', $userId);
        if ($deleted) {
            $this->line("    🗑️  Deleted livestream document");
        }

        // 3. Update MySQL is_live_now flag
        $user->is_live_now = 0;
        $user->save();
        $this->line("    💾 Updated MySQL: is_live_now = 0");

        // Log the cleanup
        Log::info("Cleaned up abandoned livestream for user {$userId} ({$user->fullname})");
    }
}
