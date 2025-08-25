<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToUserGiftInventoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_gift_inventory', function (Blueprint $table) {
            // Add foreign key constraint for gift_id (user_id and received_from_user_id already exist)
            $table->foreign('gift_id')->references('id')->on('gifts')->onDelete('cascade');
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
            // Drop foreign key constraint for gift_id
            $table->dropForeign(['gift_id']);
        });
    }
}
