<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToLikeProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('like_profiles', function (Blueprint $table) {
            // Add indexes first (required for foreign keys)
            $table->index('my_user_id');
            $table->index('user_id');
            
            // Add foreign key constraints
            $table->foreign('my_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
                  
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('like_profiles', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['my_user_id']);
            $table->dropForeign(['user_id']);
            
            // Drop indexes
            $table->dropIndex(['my_user_id']);
            $table->dropIndex(['user_id']);
        });
    }
}
