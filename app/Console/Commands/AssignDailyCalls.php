<?php

namespace App\Console\Commands;

use App\Models\TblCcPhoneCollection;
use App\Models\User;
use App\Models\DutyRoster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class AssignDailyCalls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calls:assign-daily {--date= : The date to assign calls for (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign daily calls to available agents using round-robin algorithm';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Increase timeout and memory for large datasets
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '512M');

        try {
            $assignmentDate = $this->option('date') ?? Carbon::now('Asia/Yangon')->toDateString();

            $this->info("Starting daily call assignment for date: {$assignmentDate}");
            $this->info('='.str_repeat('=', 60));

            // Get assigner user authUserId (first user)
            $assignedBy = 1;

            if (!$assignedBy) {
                $this->error('No users found in system. Please create at least one user first.');
                return self::FAILURE;
            }

            $this->info("Assigned by: User with authUserId = {$assignedBy}");

            DB::beginTransaction();

            // Step 1: Get all parent batches (batches where parentBatchId IS NULL)
            $parentBatches = \App\Models\TblCcBatch::whereNull('parentBatchId')
                ->where('batchActive', true)
                ->get(); // Get full objects, not just IDs

            if ($parentBatches->isEmpty()) {
                $this->warn('No parent batches found for assignment.');
                return self::SUCCESS;
            }

            $this->info("Found " . $parentBatches->count() . " parent batches to process");
            $this->newLine();

            $allAssignments = [];
            $totalCallsAssigned = 0;

            // Step 2: Process each parent batch separately
            foreach ($parentBatches as $parentBatch) {
                $parentBatchId = $parentBatch->batchId;

                // ✅ SKIP batch 1 (past-due) - will be assigned via team level config API
                if ($parentBatchId == 1) {
                    $this->warn("  ⭐️  Skipping Batch 1 (past-due) - should be assigned via team level config API");
                    $this->newLine();
                    continue;
                }

                $this->info("📦 Processing Parent Batch {$parentBatchId} ({$parentBatch->batchName})");
                $this->info(str_repeat('-', 60));

                // Check if this batch has intensity (self-contained) or has sub-batches
                if ($parentBatch->intensity !== null) {
                    // ===== CASE 1: Batch has intensity (e.g., batch 2, 3, 4) =====
                    $this->info("  📌 Type: Self-contained batch (has intensity)");

                    // Get available agents for this batch
                    $availableAgents = DutyRoster::getAvailableAgentsForDate($assignmentDate, $parentBatchId);

                    if ($availableAgents->isEmpty()) {
                        $this->warn("  ⚠️  No agents available for batch {$parentBatchId} on {$assignmentDate}");
                        $this->warn("  Please ensure duty roster is created for this batch.");
                        $this->newLine();
                        continue;
                    }

                    $this->info("  ✓ Found {$availableAgents->count()} available agents");

                    // Get calls to assign for this batch (use batchId directly)
                    $callsToAssign = TblCcPhoneCollection::where('batchId', $parentBatchId)
                        ->whereNull('assignedTo')
                        ->orderBy('daysOverdueGross', 'asc')
                        ->orderBy('createdAt', 'asc')
                        ->get();

                    if ($callsToAssign->isEmpty()) {
                        $this->warn("  ℹ️  No unassigned calls found for batch {$parentBatchId}");
                        $this->newLine();
                        continue;
                    }

                    $this->info("  ✓ Found {$callsToAssign->count()} calls to assign");

                } else {
                    // ===== CASE 2: Batch has NO intensity (e.g., batch 8) =====
                    $this->info("  📌 Type: Parent batch with sub-batches (no intensity)");

                    // Get sub-batches for this parent batch
                    $subBatches = \App\Models\TblCcBatch::where('parentBatchId', $parentBatchId)
                        ->where('batchActive', true)
                        ->whereNotNull('intensity')
                        ->get();

                    if ($subBatches->isEmpty()) {
                        $this->warn("  ⚠️  No active sub-batches found for parent batch {$parentBatchId}");
                        $this->newLine();
                        continue;
                    }

                    $subBatchIds = $subBatches->pluck('batchId')->toArray();
                    $subBatchNames = $subBatches->pluck('batchName')->toArray();

                    $this->info("  ✓ Found " . count($subBatchIds) . " sub-batches:");
                    foreach ($subBatches as $subBatch) {
                        $this->info("     - Batch {$subBatch->batchId}: {$subBatch->batchName}");
                    }

                    // Get available agents for this parent batch (duty roster is for parent)
                    $availableAgents = DutyRoster::getAvailableAgentsForDate($assignmentDate, $parentBatchId);

                    if ($availableAgents->isEmpty()) {
                        $this->warn("  ⚠️  No agents available for parent batch {$parentBatchId} on {$assignmentDate}");
                        $this->warn("  Please ensure duty roster is created for this parent batch.");
                        $this->newLine();
                        continue;
                    }

                    $this->info("  ✓ Found {$availableAgents->count()} available agents");

                    // Get calls to assign from ALL sub-batches (using subBatchId)
                    $callsToAssign = TblCcPhoneCollection::whereIn('subBatchId', $subBatchIds)
                        ->whereNull('assignedTo')
                        ->orderBy('daysOverdueGross', 'asc')
                        ->orderBy('createdAt', 'asc')
                        ->get();

                    if ($callsToAssign->isEmpty()) {
                        $this->warn("  ℹ️  No unassigned calls found for sub-batches: " . implode(', ', $subBatchIds));
                        $this->newLine();
                        continue;
                    }

                    $this->info("  ✓ Found {$callsToAssign->count()} calls to assign from sub-batches");
                }

                // Perform round-robin assignment
                $this->info("  🔄 Assigning calls...");
                $bar = $this->output->createProgressBar($callsToAssign->count());
                $bar->start();

                $batchAssignments = $this->performRoundRobinAssignment(
                    $callsToAssign,
                    $availableAgents,
                    $assignedBy,
                    $assignmentDate,
                    $parentBatchId,
                    $bar
                );

                $bar->finish();
                $this->newLine();

                $allAssignments[$parentBatchId] = $batchAssignments;
                $totalCallsAssigned += count($batchAssignments);

                $this->info("  ✅ Assigned {$callsToAssign->count()} calls to {$availableAgents->count()} agents for batch {$parentBatchId}");
                $this->newLine();
            }

            // ✅ Update isAssigned = true for duty rosters BEFORE commit (inside transaction)
            foreach ($allAssignments as $batchId => $assignments) {
                if ($batchId != 1 && !empty($assignments)) {  // Skip batch 1, only if has assignments
                    $updatedCount = DutyRoster::where('work_date', $assignmentDate)
                        ->where('batchId', $batchId)
                        ->update(['isAssigned' => true]);

                    $this->info("  ✅ Marked {$updatedCount} duty roster records for batch {$batchId} as assigned");
                }
            }

            DB::commit();

            // Step 4: Display results
            $this->displayBatchAssignmentResults($allAssignments);


            Log::info('Daily call assignment completed via command', [
                'assignment_date' => $assignmentDate,
                'total_calls' => $totalCallsAssigned,
                'total_batches' => count($allAssignments),
                'command_user' => 'system'
            ]);

            $this->info('✅ Daily call assignment completed successfully!');
            return self::SUCCESS;

        } catch (Exception $e) {
            DB::rollBack();

            $this->error('❌ Call assignment failed: ' . $e->getMessage());

            Log::error('Daily call assignment command failed', [
                'assignment_date' => $assignmentDate ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Perform round-robin assignment
     *
     * @param \Illuminate\Support\Collection $calls
     * @param \Illuminate\Support\Collection $agents
     * @param string $assignedBy authUserId
     * @param string $assignmentDate
     * @param mixed $progressBar
     * @return array
     */
    private function performRoundRobinAssignment($calls, $agents, int $assignedBy, string $assignmentDate, int $batchId, $progressBar = null): array
    {
        $assignments = [];
        $agentIndex = 0;
        $agentsArray = $agents->toArray();
        $totalAgents = count($agentsArray);

        foreach ($calls as $index => $call) {
            $currentAgent = $agentsArray[$agentIndex];

            // Update call with assignment
            $call->update([
                'assignedTo' => $currentAgent['authUserId'],  // ✅ SỬA: dùng authUserId thay vì id
                'assignedBy' => $assignedBy,
                'assignedAt' => now(),
                'status' => 'pending',
                'updatedBy' => $assignedBy,
            ]);

            // Log assignment
            DB::table('tbl_CcCallAssignmentLog')->insert([
                'phoneCollectionId' => $call->phoneCollectionId,
                'contractNo' => $call->contractNo,
                'batchId' => $batchId,
                'subBatchId' => $call->subBatchId,
                'action' => 'auto_assign',
                'assignedTo' => $currentAgent['authUserId'],
                'assignedBy' => $assignedBy,
                'previousAssignedTo' => null,
                'assignmentDate' => $assignmentDate,
                'assignedAt' => now(),
                'reason' => 'Auto assignment via command',
                'createdAt' => now(),
                'createdBy' => $assignedBy
            ]);

            $assignments[] = [
                'phoneCollectionId' => $call->phoneCollectionId,
                'contractNo' => $call->contractNo,
                'agent_id' => $currentAgent['id'],              // Local id để display
                'agent_auth_user_id' => $currentAgent['authUserId'],  // AuthUserId để log
                'agent_name' => $currentAgent['userFullName'],
                'batch_id' => $batchId,
                'dpd' => $call->daysOverdueGross,
                'sequence' => $index + 1
            ];

            // Move to next agent (round-robin)
            $agentIndex = ($agentIndex + 1) % $totalAgents;

            // Update progress bar
            if ($progressBar) {
                $progressBar->advance();
            }
        }

        return $assignments;
    }

    /**
     * Display batch assignment results
     */
    private function displayBatchAssignmentResults(array $allAssignments): void
    {
        $this->newLine();
        $this->info('📊 Assignment Summary by Batch:');
        $this->info('='.str_repeat('=', 80));

        $grandTotal = 0;

        foreach ($allAssignments as $batchId => $assignments) {
            $this->newLine();
            $this->info("📦 Batch {$batchId}:");
            $this->info('-'.str_repeat('-', 80));

            // Calculate summary by agent
            $summary = [];
            foreach ($assignments as $assignment) {
                $agentId = $assignment['agent_id'];
                if (!isset($summary[$agentId])) {
                    $summary[$agentId] = [
                        'name' => $assignment['agent_name'],
                        'count' => 0
                    ];
                }
                $summary[$agentId]['count']++;
            }

            // Display summary table
            $tableData = [];
            foreach ($summary as $agentId => $data) {
                $tableData[] = [
                    'Agent ID' => $agentId,
                    'Agent Name' => $data['name'],
                    'Calls Assigned' => $data['count']
                ];
            }

            $this->table(
                ['Agent ID', 'Agent Name', 'Calls Assigned'],
                $tableData
            );

            $batchTotal = count($assignments);
            $grandTotal += $batchTotal;
            $averagePerAgent = count($summary) > 0 ? round($batchTotal / count($summary), 1) : 0;
            $this->info("  Total for Batch {$batchId}: {$batchTotal} calls");
            $this->info("  Average per agent: {$averagePerAgent} calls");
        }

        $this->newLine();
        $this->info('='.str_repeat('=', 80));
        $this->info("🎯 GRAND TOTAL: {$grandTotal} calls assigned across " . count($allAssignments) . " batches");
    }
}
