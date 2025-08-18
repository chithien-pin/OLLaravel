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
            $table->integer('user_id');
            $table->enum('role_type', ['VIP', 'Millionaire', 'Billionaire', 'Celebrity']);
            $table->tinyInteger('role_type_id')->comment('1=VIP, 2=Millionaire, 3=Billionaire, 4=Celebrity');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->comment('NULL for permanent roles');
            $table->boolean('is_active')->default(true);
            $table->integer('granted_by_admin_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('granted_by_admin_id')->references('user_id')->on('admin_user')->onDelete('cascade');
            
            // Index for performance
            $table->index(['user_id', 'is_active']);
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
