<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePendingMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pending_messages', function (Blueprint $table) {
            $table->id();
            $table->string('live_session_id')->index();
            $table->unsignedInteger('sender_user_id')->index();
            $table->unsignedInteger('streamer_user_id')->index();
            $table->text('message_content');
            $table->enum('message_type', ['text', 'gift'])->default('text');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            // Note: Foreign key constraints removed due to column type mismatch
            // $table->foreign('sender_user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('streamer_user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Add indexes for better performance
            $table->index(['live_session_id', 'status']);
            $table->index(['streamer_user_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pending_messages');
    }
}