<?php
// Script to register Cloudflare webhook
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/app.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CloudflareStreamService;

// Get the app URL and construct webhook URL
$appUrl = 'https://app.romsocial.com'; // Your production URL
$webhookUrl = $appUrl . '/api/cloudflare/webhook';

echo "Registering webhook URL: $webhookUrl\n";

// Create service instance and register webhook
$cloudflareService = new CloudflareStreamService();
// First, try to get existing webhook
echo "Checking existing webhook...\n";
$checkResponse = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . config('cloudflare.api_token'),
])->get('https://api.cloudflare.com/client/v4/accounts/' . config('cloudflare.account_id') . '/stream/webhook');

if ($checkResponse->successful()) {
    $existingWebhook = $checkResponse->json();
    echo "Current webhook configuration:\n";
    print_r($existingWebhook);
}

// Try to register or update webhook
$result = $cloudflareService->createWebhookSubscription($webhookUrl);

if ($result['success']) {
    echo "✅ Webhook registered successfully!\n";
    echo "Webhook details:\n";
    print_r($result['data']);
} else {
    echo "❌ Failed to register webhook: " . $result['error'] . "\n";

    // Try with curl command directly
    echo "\nTrying with direct API call...\n";
    $accountId = config('cloudflare.account_id');
    $apiToken = config('cloudflare.api_token');

    $curl = "curl -X PUT \"https://api.cloudflare.com/client/v4/accounts/$accountId/stream/webhook\" \
        -H \"Authorization: Bearer $apiToken\" \
        -H \"Content-Type: application/json\" \
        --data '{\"notificationUrl\":\"$webhookUrl\"}'";

    echo "Executing: $curl\n";
    $output = shell_exec($curl . " 2>&1");
    echo "Response: $output\n";
}