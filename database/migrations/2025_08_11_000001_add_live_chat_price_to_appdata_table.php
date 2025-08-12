<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLiveChatPriceToAppdataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appdata', function (Blueprint $table) {
            $table->integer('live_chat_price')->default(10)->after('live_watching_price');
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
            $table->dropColumn('live_chat_price');
        });
    }
}