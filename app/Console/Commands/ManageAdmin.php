<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageAdmin extends Command
{
    protected $signature = 'admin:role
        {--grant= : Grant admin role to user ID}
        {--revoke= : Revoke admin role from user ID}
        {--list : List all admin users}';

    protected $description = 'Manage app admin roles';

    public function handle()
    {
        if ($userId = $this->option('grant')) {
            return $this->grantAdmin((int)$userId);
        }

        if ($userId = $this->option('revoke')) {
            return $this->revokeAdmin((int)$userId);
        }

        if ($this->option('list')) {
            return $this->listAdmins();
        }

        $this->info('Usage: admin:role --grant=123 | --revoke=123 | --list');
    }

    private function grantAdmin(int $userId)
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            $this->error("User ID {$userId} not found.");
            return;
        }

        if ($user->is_admin == 1) {
            $this->warn("User #{$userId} ({$user->fullname}) is already admin.");
            return;
        }

        DB::table('users')->where('id', $userId)->update(['is_admin' => 1]);
        $this->info("✅ Granted admin role to #{$userId} ({$user->fullname} - {$user->identity})");
    }

    private function revokeAdmin(int $userId)
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            $this->error("User ID {$userId} not found.");
            return;
        }

        if ($user->is_admin == 0) {
            $this->warn("User #{$userId} ({$user->fullname}) is not admin.");
            return;
        }

        DB::table('users')->where('id', $userId)->update(['is_admin' => 0]);
        $this->info("✅ Revoked admin role from #{$userId} ({$user->fullname})");
    }

    private function listAdmins()
    {
        $admins = DB::table('users')
            ->where('is_admin', 1)
            ->select('id', 'fullname', 'identity', 'username')
            ->get();

        if ($admins->isEmpty()) {
            $this->info('No admin users.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Username'],
            $admins->map(fn($u) => [$u->id, $u->fullname, $u->identity, $u->username])
        );
    }
}
