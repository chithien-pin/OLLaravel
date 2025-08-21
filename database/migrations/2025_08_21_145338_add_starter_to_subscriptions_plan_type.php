<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddStarterToSubscriptionsPlanType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Modify the enum to include 'starter'
        DB::statement("ALTER TABLE subscriptions MODIFY plan_type ENUM('starter', 'monthly', 'yearly')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove 'starter' from enum (only if no records use it)
        DB::statement("ALTER TABLE subscriptions MODIFY plan_type ENUM('monthly', 'yearly')");
    }
}
