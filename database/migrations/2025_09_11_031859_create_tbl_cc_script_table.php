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
        Schema::create('tbl_cc_script', function (Blueprint $table) {
            $table->id('scriptId'); // Primary key auto increment

            // Foreign key for communication tool (will be linked later)
            $table->unsignedBigInteger('communicationToolId')->nullable(); // Will be foreign key later

            // Enum fields with predefined values
            $table->enum('source', ['normal', 'dslp'])->default('normal');
            $table->enum('segment', ['pre-due', 'past-due'])->default('pre-due');
            $table->enum('receiver', ['rpc', 'tpc'])->default('rpc');

            // Days past due range
            $table->integer('daysPastDueFrom')->default(0);
            $table->integer('dayPastDueTo')->default(0);

            // Script content in different languages
            $table->text('scriptContentBur')->nullable(); // Burmese content
            $table->text('scriptContentEng')->nullable(); // English content
            $table->text('scriptRemark')->nullable(); // Remarks/notes

            // Status and deactivation
            $table->boolean('scriptActive')->default(true);
            $table->timestamp('dtDeactivated')->nullable();
            $table->unsignedBigInteger('personDeactivate')->nullable(); // Will be foreign key later

            // Audit fields - using nullable for foreign keys since tables don't exist yet
            $table->timestamp('dtCreated')->useCurrent();
            $table->unsignedBigInteger('personCreated')->nullable(); // Will be foreign key later
            $table->timestamp('dtUpdated')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('personUpdated')->nullable(); // Will be foreign key later
            $table->timestamp('dtDeleted')->nullable(); // Soft delete timestamp
            $table->unsignedBigInteger('personDeleted')->nullable(); // Will be foreign key later

            // Indexes for better query performance
            $table->index(['source', 'segment', 'receiver']);
            $table->index(['scriptActive', 'source']);
            $table->index(['daysPastDueFrom', 'dayPastDueTo']);
            $table->index('communicationToolId');
            $table->index('dtCreated');
            $table->index('dtDeleted'); // For soft delete queries
            $table->index('dtDeactivated');

            // TODO: Add foreign key constraints when related tables are created
            // $table->foreign('communicationToolId')->references('id')->on('communication_tools');
            // $table->foreign('personCreated')->references('id')->on('users');
            // $table->foreign('personUpdated')->references('id')->on('users');
            // $table->foreign('personDeleted')->references('id')->on('users');
            // $table->foreign('personDeactivate')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_cc_script');
    }
};
