<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ModifyReceivedFromUserIdToJsonArray extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop foreign key if it exists
        try {
            Schema::table('user_gift_inventory', function (Blueprint $table) {
                $table->dropForeign(['received_from_user_id']);
            });
        } catch (\Exception $e) {
            // Foreign key doesn't exist, continue
        }

        // First change column type to TEXT to handle conversion
        Schema::table('user_gift_inventory', function (Blueprint $table) {
            $table->text('received_from_user_id')->nullable()->change();
        });

        // Convert existing data to JSON array format
        DB::statement("
            UPDATE user_gift_inventory 
            SET received_from_user_id = CASE 
                WHEN received_from_user_id IS NOT NULL AND JSON_VALID(received_from_user_id) = 0
                THEN JSON_ARRAY(CAST(received_from_user_id AS SIGNED))
                WHEN received_from_user_id IS NOT NULL AND JSON_VALID(received_from_user_id) = 1
                THEN received_from_user_id
                ELSE JSON_ARRAY()
            END
        ");

        // Finally change to JSON type
        Schema::table('user_gift_inventory', function (Blueprint $table) {
            $table->json('received_from_user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_gift_inventory', function (Blueprint $table) {
            // Convert back to integer (get first element from JSON array)
            $table->integer('received_from_user_id')->nullable()->change();
        });

        DB::statement("
            UPDATE user_gift_inventory 
            SET received_from_user_id = JSON_EXTRACT(received_from_user_id, '$[0]')
            WHERE JSON_LENGTH(received_from_user_id) > 0
        ");

        Schema::table('user_gift_inventory', function (Blueprint $table) {
            // Re-add foreign key
            $table->foreign('received_from_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
}
