<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToGiftsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->string('category', 50)->default('general')->after('coin_price');
            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common')->after('category');
            $table->boolean('is_active')->default(true)->after('rarity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->dropColumn(['category', 'rarity', 'is_active']);
        });
    }
}