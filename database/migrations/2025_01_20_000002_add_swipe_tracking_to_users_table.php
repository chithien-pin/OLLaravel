<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSwipeTrackingToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('daily_swipes')->default(0)->after('wallet')->comment('Number of swipes made today');
            $table->date('last_swipe_date')->nullable()->after('daily_swipes')->comment('Date of last swipe action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_swipes', 'last_swipe_date']);
        });
    }
}