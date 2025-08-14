<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateMaxPendingMessagesDefaultTo5 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update existing records that have the old default value of 3 to new default of 5
        DB::table('appdata')
            ->where('max_pending_messages_per_user', 3)
            ->update(['max_pending_messages_per_user' => 5]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert back to old default value of 3
        DB::table('appdata')
            ->where('max_pending_messages_per_user', 5)
            ->update(['max_pending_messages_per_user' => 3]);
    }
}
