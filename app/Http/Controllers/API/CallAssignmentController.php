<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\User;
use App\Models\DutyRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class CallAssignmentController extends Controller
{
    /**
     * Assign calls to available agents using round-robin algorithm
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignCalls(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'assignment_date' => 'nullable|date'
            ]);

            $assignmentDate = $request->assignment_date ?? Carbon::today()->toDateString();

            // Get current user as assigner (default to first user if not authenticated)
            $assignedBy = User::first()?->id;

            if (!$assignedBy) {
                throw new Exception('No users found in system. Please create at least one user first.');
            }

            Log::info('Starting call assignment process', [
                'assignment_date' => $assignmentDate,
                'assigned_by' => $assignedBy
            ]);

            DB::beginTransaction();

            // Step 1: Get available agents from duty roster for the date
            $availableAgents = DutyRoster::getAvailableAgentsForDate($assignmentDate);

            if ($availableAgents->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No agents available for assignment',
                    'error' => "No duty roster found for date: {$assignmentDate}"
                ], 400);
            }

            // Step 2: Get calls to assign (all calls except completed)
            $callsToAssign = Call::where('status', '!=', Call::STATUS_COMPLETED)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($callsToAssign->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No calls available for assignment',
                    'error' => 'All calls are already completed'
                ], 400);
            }

            // Step 3: Reset all calls to unassigned (for clean reassignment)
            $this->resetCallsToUnassigned($callsToAssign, $assignedBy);

            // Step 4: Perform round-robin assignment
            $assignmentResults = $this->performRoundRobinAssignment(
                $callsToAssign,
                $availableAgents,
                $assignedBy,
                $assignmentDate
            );

            DB::commit();

            // Step 5: Calculate assignment summary
            $summary = $this->calculateAssignmentSummary($assignmentResults, $availableAgents);

            Log::info('Call assignment completed successfully', [
                'assignment_date' => $assignmentDate,
                'total_calls' => $callsToAssign->count(),
                'total_agents' => $availableAgents->count(),
                'assignments_made' => count($assignmentResults)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call assignment completed successfully',
                'data' => [
                    'assignment_date' => $assignmentDate,
                    'total_calls' => $callsToAssign->count(),
                    'total_agents' => $availableAgents->count(),
                    'assignments' => $assignmentResults,
                    'summary' => $summary
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Call assignment failed', [
                'assignment_date' => $assignmentDate ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Call assignment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset calls to unassigned status (Option A approach)
     *
     * @param \Illuminate\Support\Collection $calls
     * @param int $updatedBy
     * @return void
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

        Log::info('Reset calls to unassigned', [
            'call_count' => count($callIds),
            'updated_by' => $updatedBy
        ]);
    }

    /**
     * Perform round-robin assignment like "chia bài tiến lên"
     *
     * @param \Illuminate\Support\Collection $calls
     * @param \Illuminate\Support\Collection $agents
     * @param int $assignedBy
     * @param string $assignmentDate
     * @return array
     */
    private function performRoundRobinAssignment($calls, $agents, int $assignedBy, string $assignmentDate): array
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
                'call_internal_id' => $call->id,
                'agent_id' => $currentAgent['id'],
                'agent_name' => $currentAgent['user_full_name'],
                'sequence' => $index + 1
            ];

            // Move to next agent (round-robin like dealing cards)
            $agentIndex = ($agentIndex + 1) % $totalAgents;
        }

        return $assignments;
    }

    /**
     * Calculate assignment summary by agent
     *
     * @param array $assignments
     * @param \Illuminate\Support\Collection $agents
     * @return array
     */
    private function calculateAssignmentSummary(array $assignments, $agents): array
    {
        $summary = [];

        // Initialize all agents with 0 calls
        foreach ($agents as $agent) {
            $summary["agent_{$agent['id']}"] = [
                'agent_id' => $agent['id'],
                'agent_name' => $agent['user_full_name'],
                'calls_assigned' => 0
            ];
        }

        // Count assignments per agent
        foreach ($assignments as $assignment) {
            $agentKey = "agent_{$assignment['agent_id']}";
            $summary[$agentKey]['calls_assigned']++;
        }

        return array_values($summary);
    }
}
