<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropPendingMessagesSystem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop pending messages table
        Schema::dropIfExists('pending_messages');
        
        // Remove pending message settings from appdata table
        if (Schema::hasTable('appdata')) {
            Schema::table('appdata', function (Blueprint $table) {
                if (Schema::hasColumn('appdata', 'allow_free_pending_messages')) {
                    $table->dropColumn('allow_free_pending_messages');
                }
                if (Schema::hasColumn('appdata', 'max_pending_messages_per_user')) {
                    $table->dropColumn('max_pending_messages_per_user');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Recreate pending messages table
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
            
            $table->index(['live_session_id', 'status']);
            $table->index(['streamer_user_id', 'status', 'created_at']);
        });
        
        // Re-add pending message settings to appdata table
        if (Schema::hasTable('appdata')) {
            Schema::table('appdata', function (Blueprint $table) {
                $table->boolean('allow_free_pending_messages')->default(true)->after('live_chat_price');
                $table->integer('max_pending_messages_per_user')->default(5)->after('allow_free_pending_messages');
            });
        }
    }
}
