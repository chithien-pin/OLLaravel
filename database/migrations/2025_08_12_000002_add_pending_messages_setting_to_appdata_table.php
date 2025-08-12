<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPendingMessagesSettingToAppdataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appdata', function (Blueprint $table) {
            $table->boolean('allow_free_pending_messages')->default(true)->after('live_chat_price');
            $table->integer('max_pending_messages_per_user')->default(3)->after('allow_free_pending_messages');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('appdata', function (Blueprint $table) {
            $table->dropColumn(['allow_free_pending_messages', 'max_pending_messages_per_user']);
        });
    }
}