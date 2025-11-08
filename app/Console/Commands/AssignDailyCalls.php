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
            $assignmentDate = $this->option('date') ?? Carbon::today()->toDateString();

            $this->info("Starting daily call assignment for date: {$assignmentDate}");
            $this->info('='.str_repeat('=', 60));

            // Get assigner user authUserId (first user)
            $assignedBy = User::first()?->authUserId;

            if (!$assignedBy) {
                $this->error('No users found in system. Please create at least one user first.');
                return self::FAILURE;
            }

            $this->info("Assigned by: User with authUserId = {$assignedBy}");

            DB::beginTransaction();

            // Step 1: Get all distinct batchIds from phone collections
            $batchIds = TblCcPhoneCollection::whereNotNull('batchId')
                ->distinct()
                ->pluck('batchId')
                ->toArray();

            if (empty($batchIds)) {
                $this->warn('No batches found in phone collections.');
                return self::SUCCESS;
            }

            $this->info("Found " . count($batchIds) . " batches to process: " . implode(', ', $batchIds));
            $this->newLine();

            $allAssignments = [];
            $totalCallsAssigned = 0;

            // Step 2: Process each batch separately
            foreach ($batchIds as $batchId) {
                // ✅ SKIP batch 1 (past-due) - will be assigned via team level config API
                if ($batchId == 1) {
                    $this->warn("  ⏭️  Skipping Batch 1 (past-due) - should be assigned via team level config API");
                    $this->newLine();
                    continue;
                }

                $this->info("📦 Processing Batch {$batchId}");
                $this->info(str_repeat('-', 60));

                // Get available agents for this batch
                $availableAgents = DutyRoster::getAvailableAgentsForDate($assignmentDate, $batchId);

                if ($availableAgents->isEmpty()) {
                    $this->warn("  ⚠️  No agents available for batch {$batchId} on {$assignmentDate}");
                    $this->warn("  Please ensure duty roster is created for this batch.");
                    $this->newLine();
                    continue;
                }

                $this->info("  ✓ Found {$availableAgents->count()} available agents for batch {$batchId}");

                // Get calls to assign for this batch
                $callsToAssign = TblCcPhoneCollection::where('batchId', $batchId)
                    ->whereNull('assignedTo')
                    ->orderBy('daysOverdueGross', 'asc')
                    ->orderBy('createdAt', 'asc')
                    ->get();

                if ($callsToAssign->isEmpty()) {
                    $this->warn("  ℹ️  No unassigned calls found for batch {$batchId}");
                    $this->newLine();
                    continue;
                }

                $this->info("  ✓ Found {$callsToAssign->count()} calls to assign for batch {$batchId}");

                // Perform round-robin assignment for this batch
                $this->info("  🔄 Assigning calls...");
                $bar = $this->output->createProgressBar($callsToAssign->count());
                $bar->start();

                $batchAssignments = $this->performRoundRobinAssignment(
                    $callsToAssign,
                    $availableAgents,
                    $assignedBy,
                    $assignmentDate,
                    $batchId,
                    $bar
                );

                $bar->finish();
                $this->newLine();

                $allAssignments[$batchId] = $batchAssignments;
                $totalCallsAssigned += count($batchAssignments);

                $this->info("  ✅ Assigned {$callsToAssign->count()} calls to {$availableAgents->count()} agents for batch {$batchId}");
                $this->newLine();
            }

            DB::commit();

            // ✅ NEW: Update isAssigned = true for duty rosters of assigned batches (except batch 1)
            foreach ($allAssignments as $batchId => $assignments) {
                if ($batchId != 1) {  // Skip batch 1
                    DutyRoster::where('work_date', $assignmentDate)
                        ->where('batchId', $batchId)
                        ->update(['isAssigned' => true]);

                    $this->info("  ✅ Marked duty roster for batch {$batchId} as assigned");
                }
            }

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
