<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SyncBannedIps extends Command
{
    protected $signature = 'ip:sync {--add= : Add an IP to ban} {--remove= : Remove an IP from ban} {--list : List all banned IPs}';
    protected $description = 'Sync banned IPs from DB to Redis, or manage banned IPs';

    public function handle()
    {
        // Add IP
        if ($ip = $this->option('add')) {
            return $this->addBan($ip);
        }

        // Remove IP
        if ($ip = $this->option('remove')) {
            return $this->removeBan($ip);
        }

        // List
        if ($this->option('list')) {
            return $this->listBans();
        }

        // Default: sync DB → Redis
        return $this->syncToRedis();
    }

    private function addBan(string $ip)
    {
        // Add to DB
        DB::table('banned_ips')->insertOrIgnore([
            'ip_address' => $ip,
            'reason' => 'Manual ban via artisan',
            'banned_at' => now(),
        ]);

        // Add to Redis
        Redis::sadd('banned_ips', $ip);

        $this->info("✅ Banned IP: {$ip} (DB + Redis)");

        // Find and ban all users with this IP
        $users = DB::table('users')
            ->where('ip_network', $ip)
            ->whereNull('banned_at')
            ->get(['id', 'fullname', 'identity']);

        if ($users->isEmpty()) {
            $this->info("   No active users found with IP {$ip}");
            return;
        }

        $this->info("   Found {$users->count()} user(s) with this IP:");
        foreach ($users as $user) {
            $this->call('user:ban', ['--ban' => $user->id]);
        }
    }

    private function removeBan(string $ip)
    {
        // Remove from DB
        DB::table('banned_ips')->where('ip_address', $ip)->delete();

        // Remove from Redis
        Redis::srem('banned_ips', $ip);

        $this->info("✅ Unbanned IP: {$ip} (DB + Redis)");
    }

    private function listBans()
    {
        $bans = DB::table('banned_ips')->orderBy('banned_at', 'desc')->get();

        if ($bans->isEmpty()) {
            $this->info('No banned IPs.');
            return;
        }

        $this->table(
            ['ID', 'IP Address', 'Reason', 'Banned At'],
            $bans->map(fn($b) => [$b->id, $b->ip_address, $b->reason, $b->banned_at])
        );

        // Also show Redis count
        $redisCount = Redis::scard('banned_ips');
        $this->info("Redis set size: {$redisCount}");
    }

    private function syncToRedis()
    {
        $ips = DB::table('banned_ips')->pluck('ip_address')->toArray();

        // Clear and rebuild Redis set
        Redis::del('banned_ips');
        if (!empty($ips)) {
            Redis::sadd('banned_ips', ...$ips);
        }

        $count = count($ips);
        $this->info("✅ Synced {$count} banned IPs from DB → Redis");
    }
}
