<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveLegacyHlsFieldsFromPostContents extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove legacy HLS processing fields that are no longer used
     * after migrating to Cloudflare Stream for video hosting.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_contents', function (Blueprint $table) {
            // Remove legacy HLS processing fields
            // These fields were used when videos were processed locally with FFmpeg
            // Now we use Cloudflare Stream exclusively for video processing
            $table->dropColumn([
                'is_hls',           // Was: Boolean flag for HLS-processed videos
                'hls_path',         // Was: Path to local HLS playlist file
                'processing_status', // Was: Status of local FFmpeg processing
                'processing_error'  // Was: Error messages from FFmpeg
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restore the legacy fields if rollback is needed.
     * Note: Data will be lost during rollback as these fields are dropped.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('post_contents', function (Blueprint $table) {
            // Restore fields in correct order after cloudflare_upload_id
            $table->boolean('is_hls')->default(false)->after('cloudflare_upload_id')
                  ->comment('Legacy: Boolean flag for HLS-processed videos');

            $table->string('hls_path')->nullable()->after('is_hls')
                  ->comment('Legacy: Path to local HLS playlist file');

            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending')->after('hls_path')
                  ->comment('Legacy: Status of local FFmpeg processing');

            $table->text('processing_error')->nullable()->after('processing_status')
                  ->comment('Legacy: Error messages from FFmpeg');
        });
    }
}
