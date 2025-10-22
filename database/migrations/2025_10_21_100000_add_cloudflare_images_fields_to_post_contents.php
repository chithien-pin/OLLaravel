<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCloudflareImagesFieldsToPostContents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_contents', function (Blueprint $table) {
            // Cloudflare Images fields
            $table->string('cloudflare_image_id')->nullable()->after('cloudflare_upload_id')->comment('Cloudflare Images ID');
            $table->text('cloudflare_image_url')->nullable()->after('cloudflare_image_id')->comment('Cloudflare Images public URL');
            $table->json('cloudflare_image_variants')->nullable()->after('cloudflare_image_url')->comment('Cloudflare Images variant URLs (thumbnail, medium, large)');

            // Index for performance
            $table->index('cloudflare_image_id', 'idx_cloudflare_image_id');
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
            // Drop index first
            $table->dropIndex('idx_cloudflare_image_id');

            // Drop columns
            $table->dropColumn([
                'cloudflare_image_id',
                'cloudflare_image_url',
                'cloudflare_image_variants',
            ]);
        });
    }
}
