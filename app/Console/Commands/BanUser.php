<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;

class BanUser extends Command
{
    protected $signature = 'user:ban
        {--ban= : Ban user by ID}
        {--unban= : Unban user by ID}
        {--list : List all banned users}
        {--info= : Show user info by ID}';

    protected $description = 'Ban/unban user accounts (DB + Firebase Auth)';

    public function handle()
    {
        if ($userId = $this->option('ban')) {
            return $this->banUser((int)$userId);
        }

        if ($userId = $this->option('unban')) {
            return $this->unbanUser((int)$userId);
        }

        if ($this->option('list')) {
            return $this->listBanned();
        }

        if ($userId = $this->option('info')) {
            return $this->showUserInfo((int)$userId);
        }

        $this->info('Usage: user:ban --ban=123 | --unban=123 | --list | --info=123');
    }

    private function banUser(int $userId)
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            $this->error("User ID {$userId} not found.");
            return;
        }

        if ($user->banned_at) {
            $this->warn("User ID {$userId} ({$user->fullname}) is already banned since {$user->banned_at}");
            return;
        }

        $now = now();

        // 1. Ban in DB + soft delete user
        DB::table('users')->where('id', $userId)->update([
            'banned_at' => $now,
            'deleted_at' => $now,
        ]);

        // 2. Soft delete all posts by this user
        $postsCount = DB::table('posts')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now]);
        $this->info("   Posts soft-deleted: {$postsCount}");

        // 3. Disable Firebase Auth
        $firebaseResult = $this->disableFirebaseUser($user->identity, true);

        $this->info("✅ Banned user #{$userId} ({$user->fullname})");
        $this->info("   Email: {$user->identity}");
        $this->info("   Firebase: " . ($firebaseResult ? 'disabled' : 'failed (manual check needed)'));
    }

    private function unbanUser(int $userId)
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            $this->error("User ID {$userId} not found.");
            return;
        }

        if (!$user->banned_at) {
            $this->warn("User ID {$userId} ({$user->fullname}) is not banned.");
            return;
        }

        // 1. Unban in DB + restore user
        DB::table('users')->where('id', $userId)->update([
            'banned_at' => null,
            'deleted_at' => null,
        ]);

        // 2. Restore all posts by this user (only those deleted at ban time)
        $postsCount = DB::table('posts')
            ->where('user_id', $userId)
            ->where('deleted_at', $user->banned_at)
            ->update(['deleted_at' => null]);

        // 3. Enable Firebase Auth
        $firebaseResult = $this->disableFirebaseUser($user->identity, false);

        $this->info("✅ Unbanned user #{$userId} ({$user->fullname})");
        $this->info("   Posts restored: {$postsCount}");
        $this->info("   Firebase: " . ($firebaseResult ? 'enabled' : 'failed (manual check needed)'));
        $this->warn("   Note: IP ban NOT automatically removed. Use: banip --remove={$user->ip_network}");
    }

    private function listBanned()
    {
        $banned = DB::table('users')
            ->whereNotNull('banned_at')
            ->select('id', 'fullname', 'identity', 'ip_network', 'banned_at')
            ->orderBy('banned_at', 'desc')
            ->get();

        if ($banned->isEmpty()) {
            $this->info('No banned users.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'IP', 'Banned At'],
            $banned->map(fn($u) => [$u->id, $u->fullname, $u->identity, $u->ip_network, $u->banned_at])
        );
    }

    private function showUserInfo(int $userId)
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            $this->error("User ID {$userId} not found.");
            return;
        }

        $this->table(['Field', 'Value'], [
            ['ID', $user->id],
            ['Name', $user->fullname],
            ['Username', $user->username],
            ['Email', $user->identity],
            ['IP', $user->ip_network ?? 'N/A'],
            ['Country', $user->country ?? 'N/A'],
            ['Is Block', $user->is_block ? 'Yes' : 'No'],
            ['Banned At', $user->banned_at ?? 'Not banned'],
            ['Deleted At', $user->deleted_at ?? 'Active'],
            ['Created At', $user->created_at ?? 'N/A'],
        ]);
    }

    /**
     * Disable/enable a Firebase Auth user via REST API
     */
    private function disableFirebaseUser(string $email, bool $disable): bool
    {
        try {
            $accessToken = $this->getFirebaseAccessToken();
            if (!$accessToken) return false;

            $client = new Client();
            $projectId = $this->getFirebaseProjectId();

            // 1. Look up user by email
            $response = $client->post(
                "https://identitytoolkit.googleapis.com/v1/accounts:lookup",
                [
                    'headers' => ['Authorization' => "Bearer {$accessToken}"],
                    'json' => ['email' => [$email]],
                ]
            );

            $body = json_decode($response->getBody(), true);
            if (empty($body['users'])) {
                $this->warn("   Firebase user not found for: {$email}");
                return false;
            }

            $firebaseUid = $body['users'][0]['localId'];

            // 2. Disable/enable user
            $response = $client->post(
                "https://identitytoolkit.googleapis.com/v1/accounts:update",
                [
                    'headers' => ['Authorization' => "Bearer {$accessToken}"],
                    'json' => [
                        'localId' => $firebaseUid,
                        'disableUser' => $disable,
                    ],
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $this->warn("   Firebase error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Firebase access token using service account
     */
    private function getFirebaseAccessToken(): ?string
    {
        try {
            $credPath = base_path('firebase-credentials.json');
            if (!file_exists($credPath)) {
                $credPath = base_path('googleCredentials.json');
            }
            if (!file_exists($credPath)) {
                $this->warn('   Firebase credentials file not found');
                return null;
            }

            $cred = json_decode(file_get_contents($credPath), true);

            $now = time();
            $payload = [
                'iss' => $cred['client_email'],
                'sub' => $cred['client_email'],
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
                'scope' => 'https://www.googleapis.com/auth/identitytoolkit https://www.googleapis.com/auth/firebase',
            ];

            $jwt = JWT::encode($payload, $cred['private_key'], 'RS256');

            $client = new Client();
            $response = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'] ?? null;
        } catch (\Exception $e) {
            $this->warn("   Token error: " . $e->getMessage());
            return null;
        }
    }

    private function getFirebaseProjectId(): string
    {
        $credPath = base_path('firebase-credentials.json');
        if (!file_exists($credPath)) {
            $credPath = base_path('googleCredentials.json');
        }
        $cred = json_decode(file_get_contents($credPath), true);
        return $cred['project_id'] ?? '';
    }
}
