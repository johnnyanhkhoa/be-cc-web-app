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
        Schema::table('duty_rosters', function (Blueprint $table) {
            // Drop notes column
            $table->dropColumn('notes');

            // Add soft delete column
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duty_rosters', function (Blueprint $table) {
            // Add notes column back
            $table->text('notes')->nullable();

            // Remove soft delete column
            $table->dropSoftDeletes();
        });
    }
};
