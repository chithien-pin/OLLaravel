<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id'); // Changed to match existing users table
            $table->enum('role_type', ['normal', 'vip'])->default('normal');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable(); // null for normal role, date for VIP
            $table->unsignedInteger('granted_by_admin_id')->nullable(); // track who granted the role
            $table->boolean('is_active')->default(true); // for revoke functionality
            $table->timestamps();

            // Foreign key constraints - removed for compatibility
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Index for performance
            $table->index(['user_id', 'is_active']);
            $table->index(['role_type', 'is_active']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_roles');
    }
}