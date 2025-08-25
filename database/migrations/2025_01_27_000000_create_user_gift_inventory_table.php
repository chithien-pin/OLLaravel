<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserGiftInventoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_gift_inventory', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id'); // Match users.id type
            $table->integer('gift_id'); // Match gifts.id type  
            $table->integer('quantity')->default(1);
            $table->integer('received_from_user_id')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->boolean('is_converted')->default(false);
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            // Foreign keys - no explicit constraint to avoid type issues
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('gift_id')->references('id')->on('gifts')->onDelete('cascade');
            // $table->foreign('received_from_user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('user_id');
            $table->index('gift_id');
            $table->index('is_converted');
            $table->index(['user_id', 'is_converted']); // Composite index for efficient queries
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_gift_inventory');
    }
}