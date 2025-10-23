<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcPhoneCollection;
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
     * Assign phone collections to available agents using round-robin algorithm
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

            // Get current user authUserId (default to first user's authUserId if not authenticated)
            $firstUser = User::first();
            $assignedBy = $firstUser?->authUserId;

            if (!$assignedBy) {
                throw new Exception('No users found in system. Please create at least one user first.');
            }

            Log::info('Starting call assignment process', [
                'assignment_date' => $assignmentDate,
                'assigned_by_auth_user_id' => $assignedBy
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

            // Step 2: Get phone collections to assign (all except completed)
            $phoneCollectionsToAssign = TblCcPhoneCollection::where('status', '!=', 'completed')
                ->whereNull('assignedTo')  // ← CHỈ lấy chưa assign
                ->orderBy('createdAt', 'asc')
                ->limit(100)
                ->get();

            if ($phoneCollectionsToAssign->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No phone collections available for assignment',
                    'error' => 'All phone collections are already completed'
                ], 400);
            }

            // Step 4: Perform round-robin assignment
            $assignmentResults = $this->performRoundRobinAssignment(
                $phoneCollectionsToAssign,
                $availableAgents,
                $assignedBy,
                $assignmentDate
            );

            DB::commit();

            // Step 5: Calculate assignment summary
            $summary = $this->calculateAssignmentSummary($assignmentResults, $availableAgents);

            Log::info('Call assignment completed successfully', [
                'assignment_date' => $assignmentDate,
                'total_phone_collections' => $phoneCollectionsToAssign->count(),
                'total_agents' => $availableAgents->count(),
                'assignments_made' => count($assignmentResults)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phone collections assigned successfully',
                'data' => [
                    'assignment_date' => $assignmentDate,
                    'total_phone_collections' => $phoneCollectionsToAssign->count(),
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
     * Reset phone collections to unassigned status
     *
     * @param \Illuminate\Support\Collection $phoneCollections
     * @param int $updatedBy
     * @return void
     */
    private function resetPhoneCollectionsToUnassigned($phoneCollections, int $updatedBy): void
    {
        $phoneCollectionIds = $phoneCollections->pluck('phoneCollectionId')->toArray();

        TblCcPhoneCollection::whereIn('phoneCollectionId', $phoneCollectionIds)->update([
            'assignedTo' => null,
            'assignedBy' => null,
            'assignedAt' => null,
            'updatedBy' => $updatedBy, // Lưu authUserId
            'updatedAt' => now(),
        ]);

        Log::info('Reset phone collections to unassigned', [
            'phone_collection_count' => count($phoneCollectionIds),
            'updated_by_auth_user_id' => $updatedBy
        ]);
    }

    /**
     * Perform round-robin assignment
     *
     * @param \Illuminate\Support\Collection $phoneCollections
     * @param \Illuminate\Support\Collection $agents
     * @param int $assignedBy
     * @param string $assignmentDate
     * @return array
     */
    private function performRoundRobinAssignment($phoneCollections, $agents, int $assignedBy, string $assignmentDate): array
    {
        $assignments = [];
        $agentIndex = 0;
        $agentsArray = $agents->toArray();
        $totalAgents = count($agentsArray);

        foreach ($phoneCollections as $index => $phoneCollection) {
            $currentAgent = $agentsArray[$agentIndex];

            // Update phone collection with assignment - Lưu authUserId
            $phoneCollection->update([
                'assignedTo' => $currentAgent['authUserId'],  // authUserId
                'assignedBy' => $assignedBy,                  // authUserId
                'assignedAt' => now(),
                'updatedBy' => $assignedBy,                   // authUserId
            ]);

            $assignments[] = [
                'phoneCollectionId' => $phoneCollection->phoneCollectionId,
                'contractId' => $phoneCollection->contractId,
                'contractNo' => $phoneCollection->contractNo,
                'customerFullName' => $phoneCollection->customerFullName,
                'agent_auth_user_id' => $currentAgent['authUserId'],
                'agent_name' => $currentAgent['userFullName'],
                'sequence' => $index + 1
            ];

            // Move to next agent (round-robin)
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

        // Initialize all agents with 0 phone collections
        foreach ($agents as $agent) {
            $summary["agent_{$agent['authUserId']}"] = [
                'agent_auth_user_id' => $agent['authUserId'],
                'agent_name' => $agent['userFullName'],
                'phone_collections_assigned' => 0
            ];
        }

        // Count assignments per agent
        foreach ($assignments as $assignment) {
            $agentKey = "agent_{$assignment['agent_auth_user_id']}";
            if (isset($summary[$agentKey])) {
                $summary[$agentKey]['phone_collections_assigned']++;
            }
        }

        return array_values($summary);
    }
}
