<?php
// Test script to verify R2 fields appear in API-like response

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Post;

echo "🧪 Testing R2 Fields in API Response\n";
echo "=====================================\n\n";

// Fetch posts similar to fetchHomePageData
$posts = Post::select('posts.id', 'posts.user_id', 'posts.description')
    ->with([
        'content' => function($query) {
            // This is the FIXED query with R2 fields
            $query->select('id', 'post_id', 'content', 'thumbnail', 'content_type', 'view_count',
                           // Cloudflare Stream fields (videos)
                           'cloudflare_video_id', 'cloudflare_stream_url', 'cloudflare_thumbnail_url',
                           'cloudflare_hls_url', 'cloudflare_dash_url', 'cloudflare_status',
                           'cloudflare_duration',
                           // Cloudflare Images fields (photos)
                           'cloudflare_image_id', 'cloudflare_image_url', 'cloudflare_image_variants',
                           // R2 Storage fields (videos) - for FREE bandwidth!
                           'r2_mp4_url', 'r2_key', 'r2_file_size', 'r2_uploaded_at',
                           'use_r2', 'r2_status')
                  ->orderBy('id', 'asc');
        }
    ])
    ->where('posts.id', '>=', 189) // Posts with R2 videos
    ->limit(3)
    ->get();

if ($posts->isEmpty()) {
    echo "❌ No posts found\n";
    exit(1);
}

foreach ($posts as $post) {
    foreach ($post->content as $content) {
        // Call transformForResponse() like the controller does
        $content->transformForResponse();

        // Convert to JSON like API response
        $json = $content->toArray();

        echo "📹 Content ID: {$content->id} (Post ID: {$post->id})\n";
        echo "   Type: " . ($json['content_type'] == 1 ? 'VIDEO' : 'IMAGE') . "\n";

        if ($json['content_type'] == 1) {
            echo "   \n";
            echo "   🔍 R2 FIELDS IN RESPONSE:\n";
            echo "   ------------------------\n";
            echo "   r2_mp4_url: " . ($json['r2_mp4_url'] ?? 'NOT PRESENT') . "\n";
            echo "   r2_status: " . ($json['r2_status'] ?? 'NOT PRESENT') . "\n";
            echo "   use_r2: " . (isset($json['use_r2']) ? ($json['use_r2'] ? 'true' : 'false') : 'NOT PRESENT') . "\n";
            echo "   r2_key: " . ($json['r2_key'] ?? 'NOT PRESENT') . "\n";
            echo "   \n";
            echo "   🎯 VIRTUAL ATTRIBUTES (from transformForResponse):\n";
            echo "   --------------------------------------------------\n";
            echo "   is_r2_available: " . (isset($json['is_r2_available']) ? ($json['is_r2_available'] ? 'true' : 'false') : 'NOT PRESENT') . "\n";
            echo "   video_source: " . ($json['video_source'] ?? 'NOT PRESENT') . "\n";

            // Determine result
            $hasR2Fields = isset($json['r2_mp4_url']) && isset($json['is_r2_available']) && isset($json['video_source']);

            if ($hasR2Fields && $json['r2_mp4_url']) {
                echo "\n   ✅ SUCCESS: R2 fields are present in JSON response!\n";
                echo "   🎉 API will return R2 URLs to Flutter app!\n";
            } elseif ($hasR2Fields && !$json['r2_mp4_url']) {
                echo "\n   ⚠️  R2 fields present but r2_mp4_url is NULL (video not archived yet)\n";
            } else {
                echo "\n   ❌ FAILED: R2 fields missing from JSON response\n";
            }
        }

        echo "\n";
        break; // Only test first content per post
    }
}

echo "=====================================\n";
echo "✅ Test complete!\n";
