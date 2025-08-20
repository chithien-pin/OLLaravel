<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSwipeLimitToAppdataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appdata', function (Blueprint $table) {
            $table->integer('swipe_limit')->default(50)->after('live_chat_price')->comment('Daily swipe limit for normal users');
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
            $table->dropColumn('swipe_limit');
        });
    }
}