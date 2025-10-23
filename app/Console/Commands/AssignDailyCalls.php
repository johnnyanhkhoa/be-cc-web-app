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

            // Step 1: Get available agents from duty roster
            $availableAgents = DutyRoster::getAvailableAgentsForDate($assignmentDate);

            if ($availableAgents->isEmpty()) {
                $this->warn("No agents available for assignment on {$assignmentDate}");
                $this->warn('Please ensure duty roster is created for this date.');
                return self::SUCCESS;
            }

            $this->info("Found {$availableAgents->count()} available agents:");
            foreach ($availableAgents as $agent) {
                $this->line("  - {$agent->userFullName} (authUserId: {$agent->authUserId})");
            }

            // Step 2: Get phone collections to assign (only unassigned, not completed)
            $callsToAssign = TblCcPhoneCollection::where('status', '!=', 'completed')
                ->whereNull('assignedTo')
                ->orderBy('createdAt', 'asc')
                ->get();

            if ($callsToAssign->isEmpty()) {
                $this->warn('No phone collections available for assignment.');
                $this->warn('All phone collections are either completed or already assigned.');
                return self::SUCCESS;
            }

            $this->info("Found {$callsToAssign->count()} phone collections to assign");

            // Step 3: Perform round-robin assignment
            $this->info('Performing round-robin assignment...');
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

            // Step 4: Display results
            $this->displayAssignmentResults($assignments, $availableAgents);

            Log::info('Daily call assignment completed via command', [
                'assignment_date' => $assignmentDate,
                'total_phone_collections' => $callsToAssign->count(),
                'total_agents' => $availableAgents->count(),
                'assigned_by_auth_user_id' => $assignedBy,
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
    private function performRoundRobinAssignment($calls, $agents, string $assignedBy, string $assignmentDate, $progressBar = null): array
    {
        $assignments = [];
        $agentIndex = 0;
        $agentsArray = $agents->toArray();
        $totalAgents = count($agentsArray);

        foreach ($calls as $index => $call) {
            $currentAgent = $agentsArray[$agentIndex];

            // Update phone collection with assignment
            $call->update([
                'assignedTo' => $currentAgent['authUserId'],
                'assignedBy' => $assignedBy,
                'assignedAt' => now(),
                'updatedBy' => $assignedBy,
            ]);

            $assignments[] = [
                'phoneCollectionId' => $call->phoneCollectionId,
                'contractId' => $call->contractId,
                'contractNo' => $call->contractNo,
                'customerFullName' => $call->customerFullName,
                'agent_auth_user_id' => $currentAgent['authUserId'],
                'agent_name' => $currentAgent['userFullName'],
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
     *
     * @param array $assignments
     * @param \Illuminate\Support\Collection $agents
     * @return void
     */
    private function displayAssignmentResults(array $assignments, $agents): void
    {
        $this->newLine();
        $this->info('📊 Assignment Results:');
        $this->info('-'.str_repeat('-', 60));

        // Calculate summary by agent using authUserId
        $summary = [];
        foreach ($agents as $agent) {
            $summary[$agent->authUserId] = [
                'name' => $agent->userFullName,
                'count' => 0
            ];
        }

        // Count assignments per agent
        foreach ($assignments as $assignment) {
            $authUserId = $assignment['agent_auth_user_id'];
            if (isset($summary[$authUserId])) {
                $summary[$authUserId]['count']++;
            }
        }

        // Display summary table
        $tableData = [];
        foreach ($summary as $authUserId => $data) {
            $tableData[] = [
                'Auth User ID' => $authUserId,
                'Agent Name' => $data['name'],
                'Calls Assigned' => $data['count']
            ];
        }

        $this->table(
            ['Auth User ID', 'Agent Name', 'Calls Assigned'],
            $tableData
        );

        $this->info("Total assignments made: " . count($assignments));
        $averagePerAgent = round(count($assignments) / count($agents), 1);
        $this->info("Average calls per agent: {$averagePerAgent}");
    }
}
