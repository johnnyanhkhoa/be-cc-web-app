<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // bigint auto_increment primary key

            // Zay Yar Integration
            $table->string('auth_user_id')->unique(); // Reference to Zay Yar user

            // Cached User Info (for performance)
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('user_full_name')->nullable();

            // Local App Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            // Laravel Standard
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at for soft delete
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
