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
        Schema::create('calls', function (Blueprint $table) {
            $table->id(); // bigint auto_increment primary key

            // Core call identification
            $table->string('call_id')->unique(); // External call reference ID

            // Call status tracking
            $table->enum('status', [
                'pending',      // Call created but not assigned
                'assigned',     // Call assigned to agent
                'reassigned',   // Call reassigned to different agent
                'in_progress',  // Agent started calling
                'completed',    // Call completed successfully
                'failed',       // Call failed/unsuccessful
                'cancelled'     // Call cancelled
            ])->default('pending');

            // Assignment tracking
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable(); // When was it assigned

            // Attempt tracking (for future use)
            $table->integer('total_attempts')->default(0); // Total number of call attempts
            $table->timestamp('last_attempt_at')->nullable(); // Last attempt timestamp
            $table->foreignId('last_attempt_by')->nullable()->constrained('users')->onDelete('set null');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            // Laravel standard timestamps + soft delete
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at

            // Indexes for performance
            $table->index(['status', 'assigned_to']);
            $table->index('assigned_at');
            $table->index('last_attempt_at');
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
