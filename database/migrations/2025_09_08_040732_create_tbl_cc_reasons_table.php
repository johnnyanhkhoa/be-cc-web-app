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
        Schema::create('tbl_cc_reason', function (Blueprint $table) {
            $table->id('reasonId'); // Primary key auto increment
            $table->string('reasonType', 100)->index(); // Index for better performance
            $table->string('reasonName', 255);
            $table->boolean('reasonActive')->default(true); // Default active
            $table->text('reasonRemark')->nullable(); // Allow null for optional remarks

            // Audit fields - using nullable for foreign keys since tables don't exist yet
            $table->timestamp('dtCreated')->useCurrent();
            $table->unsignedBigInteger('personCreated')->nullable(); // Will be foreign key later
            $table->timestamp('dtUpdated')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('personUpdated')->nullable(); // Will be foreign key later
            $table->timestamp('dtDeleted')->nullable(); // Soft delete timestamp
            $table->unsignedBigInteger('personDeleted')->nullable(); // Will be foreign key later

            // Indexes for better query performance
            $table->index(['reasonType', 'reasonActive']);
            $table->index('dtCreated');
            $table->index('dtDeleted'); // For soft delete queries

            // TODO: Add foreign key constraints when user/person tables are created
            // $table->foreign('personCreated')->references('id')->on('users');
            // $table->foreign('personUpdated')->references('id')->on('users');
            // $table->foreign('personDeleted')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_cc_reason');
    }
};
