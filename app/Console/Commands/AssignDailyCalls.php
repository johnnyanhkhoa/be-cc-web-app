<?php

namespace App\Console\Commands;

use App\Models\Call;
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
        try {
            $assignmentDate = $this->option('date') ?? Carbon::today()->toDateString();

            $this->info("Starting daily call assignment for date: {$assignmentDate}");
            $this->info('='.str_repeat('=', 60));

            // Get assigner user (first user)
            $assignedBy = User::first()?->id;

            if (!$assignedBy) {
                $this->error('No users found in system. Please create at least one user first.');
                return self::FAILURE;
            }

            DB::beginTransaction();

            // Step 1: Get available agents
            $availableAgents = DutyRoster::getAvailableAgentsForDate($assignmentDate);

            if ($availableAgents->isEmpty()) {
                $this->warn("No agents available for assignment on {$assignmentDate}");
                $this->warn('Please ensure duty roster is created for this date.');
                return self::SUCCESS;
            }

            $this->info("Found {$availableAgents->count()} available agents:");
            foreach ($availableAgents as $agent) {
                $this->line("  - {$agent['user_full_name']} (ID: {$agent['id']})");
            }

            // Step 2: Get calls to assign
            $callsToAssign = Call::where('status', '!=', Call::STATUS_COMPLETED)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($callsToAssign->isEmpty()) {
                $this->warn('No calls available for assignment - all calls are completed.');
                return self::SUCCESS;
            }

            $this->info("Found {$callsToAssign->count()} calls to assign");

            // Step 3: Reset all calls to unassigned
            $this->info('Resetting all calls to unassigned status...');
            $this->resetCallsToUnassigned($callsToAssign, $assignedBy);

            // Step 4: Perform round-robin assignment
            $this->info('Performing round-robin call assignment...');
            $bar = $this->output->createProgressBar($callsToAssign->count());
            $bar->start();

            $assignments = $this->performRoundRobinAssignment(
                $callsToAssign,
                $availableAgents,
                $assignedBy,
                $assignmentDate,
                $bar
            );

            $bar->finish();
            $this->newLine();

            DB::commit();

            // Step 5: Display results
            $this->displayAssignmentResults($assignments, $availableAgents);

            Log::info('Daily call assignment completed via command', [
                'assignment_date' => $assignmentDate,
                'total_calls' => $callsToAssign->count(),
                'total_agents' => $availableAgents->count(),
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
     * Reset calls to unassigned status
     */
    private function resetCallsToUnassigned($calls, int $updatedBy): void
    {
        $callIds = $calls->pluck('id')->toArray();

        Call::whereIn('id', $callIds)->update([
            'assigned_to' => null,
            'assigned_by' => null,
            'assigned_at' => null,
            'status' => Call::STATUS_PENDING,
            'updated_by' => $updatedBy,
            'updated_at' => now(),
        ]);
    }

    /**
     * Perform round-robin assignment
     */
    private function performRoundRobinAssignment($calls, $agents, int $assignedBy, string $assignmentDate, $progressBar = null): array
    {
        $assignments = [];
        $agentIndex = 0;
        $agentsArray = $agents->toArray();
        $totalAgents = count($agentsArray);

        foreach ($calls as $index => $call) {
            $currentAgent = $agentsArray[$agentIndex];

            // Update call with assignment
            $call->update([
                'assigned_to' => $currentAgent['id'],
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'status' => Call::STATUS_ASSIGNED,
                'updated_by' => $assignedBy,
            ]);

            $assignments[] = [
                'call_id' => $call->call_id,
                'agent_id' => $currentAgent['id'],
                'agent_name' => $currentAgent['user_full_name'],
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
     * Display assignment results in a nice table format
     */
    private function displayAssignmentResults(array $assignments, $agents): void
    {
        $this->newLine();
        $this->info('📊 Assignment Results:');
        $this->info('-'.str_repeat('-', 60));

        // Calculate summary by agent
        $summary = [];
        foreach ($agents as $agent) {
            $summary[$agent['id']] = [
                'name' => $agent['user_full_name'],
                'count' => 0
            ];
        }

        foreach ($assignments as $assignment) {
            $summary[$assignment['agent_id']]['count']++;
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

        $this->info("Total assignments made: " . count($assignments));
        $averagePerAgent = round(count($assignments) / count($agents), 1);
        $this->info("Average calls per agent: {$averagePerAgent}");
    }
}
