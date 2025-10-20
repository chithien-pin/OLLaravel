<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCloudflareStreamFieldsToPostContentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_contents', function (Blueprint $table) {
            // Cloudflare Stream fields
            $table->string('cloudflare_video_id')->nullable()->after('content_type')->comment('Cloudflare Stream video UID');
            $table->string('cloudflare_stream_url')->nullable()->after('cloudflare_video_id')->comment('Cloudflare Stream playback URL');
            $table->string('cloudflare_thumbnail_url')->nullable()->after('cloudflare_stream_url')->comment('Cloudflare Stream thumbnail URL');
            $table->string('cloudflare_hls_url')->nullable()->after('cloudflare_thumbnail_url')->comment('Cloudflare HLS manifest URL');
            $table->string('cloudflare_dash_url')->nullable()->after('cloudflare_hls_url')->comment('Cloudflare DASH manifest URL');
            $table->enum('cloudflare_status', ['pending', 'uploading', 'processing', 'ready', 'error'])->nullable()->after('cloudflare_dash_url')->comment('Video processing status');
            $table->text('cloudflare_error')->nullable()->after('cloudflare_status')->comment('Error message if any');
            $table->integer('cloudflare_duration')->nullable()->after('cloudflare_error')->comment('Video duration in seconds');
            $table->string('cloudflare_upload_id')->nullable()->after('cloudflare_duration')->comment('TUS upload ID for tracking');

            // Indexes for performance
            $table->index('cloudflare_video_id', 'idx_cloudflare_video_id');
            $table->index('cloudflare_status', 'idx_cloudflare_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('post_contents', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_cloudflare_video_id');
            $table->dropIndex('idx_cloudflare_status');

            // Drop columns
            $table->dropColumn([
                'cloudflare_video_id',
                'cloudflare_stream_url',
                'cloudflare_thumbnail_url',
                'cloudflare_hls_url',
                'cloudflare_dash_url',
                'cloudflare_status',
                'cloudflare_error',
                'cloudflare_duration',
                'cloudflare_upload_id'
            ]);
        });
    }
}
