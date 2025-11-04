<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddR2FieldsToPostContentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_contents', function (Blueprint $table) {
            // R2 storage fields
            $table->string('r2_mp4_url', 500)->nullable()->after('cloudflare_hls_url')
                ->comment('Direct MP4 URL from R2 bucket for free bandwidth');

            $table->string('r2_key', 255)->nullable()->after('r2_mp4_url')
                ->comment('R2 object key (path in bucket)');

            $table->bigInteger('r2_file_size')->nullable()->after('r2_key')
                ->comment('File size in bytes on R2');

            $table->timestamp('r2_uploaded_at')->nullable()->after('r2_file_size')
                ->comment('When video was uploaded to R2');

            $table->boolean('use_r2')->default(false)->after('r2_uploaded_at')
                ->comment('Whether to prefer R2 over Stream for this video');

            $table->string('r2_status', 50)->nullable()->after('use_r2')
                ->comment('R2 archive status: pending, processing, ready, failed');

            // Indexes for faster queries
            $table->index('r2_status');
            $table->index('use_r2');
            $table->index('r2_uploaded_at');
        });

        // Add comment to table
        DB::statement("ALTER TABLE `post_contents` COMMENT = 'Post content with Cloudflare Stream HLS and R2 MP4 hybrid storage'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('post_contents', function (Blueprint $table) {
            $table->dropColumn([
                'r2_mp4_url',
                'r2_key',
                'r2_file_size',
                'r2_uploaded_at',
                'use_r2',
                'r2_status'
            ]);
        });
    }
}