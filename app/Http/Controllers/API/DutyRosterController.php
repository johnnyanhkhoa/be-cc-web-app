<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DutyRosterRequest;
use App\Models\DutyRoster;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DutyRosterController extends Controller
{
    /**
     * Get duty roster data for date range
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDutyRoster(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date'
            ]);

            $fromDate = Carbon::parse($request->from_date);
            $toDate = Carbon::parse($request->to_date);

            Log::info('Fetching duty roster for date range', [
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString()
            ]);

            // Get duty roster data
            $dutyData = DutyRoster::getDutyRosterData(
                $fromDate->toDateString(),
                $toDate->toDateString()
            );

            // Check if any data exists
            $hasData = collect($dutyData)->some(function ($day) {
                return !empty($day['agents']);
            });

            if (!$hasData) {
                return response()->json([
                    'success' => true,
                    'message' => 'No duty roster assignments found for the selected date range',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Duty roster retrieved successfully',
                'data' => [
                    'from_date' => $fromDate->toDateString(),
                    'to_date' => $toDate->toDateString(),
                    'days' => $dutyData,
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get duty roster', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve duty roster',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create duty roster assignments
     *
     * @param DutyRosterRequest $request
     * @return JsonResponse
     */
    public function createDutyRoster(DutyRosterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            Log::info('Creating duty roster assignments', [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'agent_ids' => $validated['agent_ids']
            ]);

            DB::beginTransaction();

            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $agentIds = $validated['agent_ids'];

            // Get current user ID - use first available user if no auth
            $createdBy = User::first()?->id;

            if (!$createdBy) {
                throw new Exception('No users found in system. Please create at least one user first.');
            }

            $created = [];
            $skipped = [];

            // Create assignments for each date and agent combination
            $current = $startDate->copy();
            while ($current <= $endDate) {
                foreach ($agentIds as $agentId) {
                    $dateStr = $current->toDateString();

                    // Check if duty roster already exists (including soft deleted)
                    $existingDuty = DutyRoster::withTrashed()
                        ->where('work_date', $dateStr)
                        ->where('user_id', $agentId)
                        ->first();

                    if ($existingDuty) {
                        if ($existingDuty->trashed()) {
                            // Restore soft deleted record
                            $existingDuty->restore();
                            $existingDuty->update([
                                'is_working' => true,
                                'created_by' => $createdBy,
                            ]);

                            $created[] = [
                                'date' => $dateStr,
                                'agent_id' => $agentId,
                                'status' => 'restored'
                            ];
                        } else {
                            // Update existing active record
                            $existingDuty->update([
                                'is_working' => true,
                                'created_by' => $createdBy,
                            ]);

                            $created[] = [
                                'date' => $dateStr,
                                'agent_id' => $agentId,
                                'status' => 'updated'
                            ];
                        }
                    } else {
                        // Create new record
                        $dutyRoster = DutyRoster::create([
                            'work_date' => $dateStr,
                            'user_id' => $agentId,
                            'is_working' => true,
                            'created_by' => $createdBy,
                        ]);

                        $created[] = [
                            'date' => $dateStr,
                            'agent_id' => $agentId,
                            'status' => 'created'
                        ];
                    }
                }
                $current->addDay();
            }

            DB::commit();

            Log::info('Duty roster assignments processed', [
                'processed_count' => count($created)
            ]);

            // Group results by status for summary
            $summary = collect($created)->groupBy('status')->map->count();

            return response()->json([
                'success' => true,
                'message' => 'Duty roster processed successfully',
                'data' => [
                    'processed' => array_map(function($item) {
                        return [
                            'date' => $item['date'],
                            'agent_id' => $item['agent_id']
                        ];
                    }, $created),
                    'summary' => $summary
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create duty roster', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create duty roster',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete agent from duty roster by user_id and date
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteFromDutyRoster(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'date' => 'required|date'
            ]);

            $userId = $request->user_id;
            $date = $request->date;

            Log::info('Attempting to delete from duty roster', [
                'user_id' => $userId,
                'date' => $date
            ]);

            $dutyRoster = DutyRoster::where('user_id', $userId)
                ->where('work_date', $date)
                ->first();

            if (!$dutyRoster) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duty roster assignment not found',
                    'error' => 'No assignment found for this user on the specified date'
                ], 404);
            }

            // Check if deletion is allowed
            if (!$dutyRoster->canBeDeleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete duty roster assignment',
                    'error' => 'Deletion is only allowed before 7:00 AM on the same day or for future dates'
                ], 403);
            }

            $agentName = $dutyRoster->user->user_full_name ?? 'Unknown';

            $dutyRoster->delete(); // This will be soft delete now

            Log::info('Duty roster assignment deleted successfully', [
                'user_id' => $userId,
                'work_date' => $date,
                'agent_name' => $agentName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent removed from duty roster successfully',
                'data' => [
                    'user_id' => $userId,
                    'date' => $date,
                    'agent_name' => $agentName
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to delete from duty roster', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove agent from duty roster',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available agents
     *
     * @return JsonResponse
     */
    public function getAvailableAgents(): JsonResponse
    {
        try {
            $agents = User::active()
                ->select('id', 'user_full_name', 'email', 'username')
                ->orderBy('user_full_name')
                ->get();

            Log::info('Retrieved available agents', [
                'count' => $agents->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Available agents retrieved successfully',
                'data' => [
                    'agents' => $agents,
                    'total_count' => $agents->count()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get available agents', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agents assigned for specific date (helper for call assignment)
     *
     * @param Request $request
     * @param string $date
     * @return JsonResponse
     */
    public function getAgentsForDate(Request $request, string $date): JsonResponse
    {
        try {
            $request->merge(['date' => $date]);
            $request->validate([
                'date' => 'required|date'
            ]);

            $agents = DutyRoster::getAvailableAgentsForDate($date);

            Log::info('Retrieved agents for specific date', [
                'date' => $date,
                'agent_count' => $agents->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agents for date retrieved successfully',
                'data' => [
                    'date' => $date,
                    'agents' => $agents->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->user_full_name,
                            'email' => $user->email,
                            'username' => $user->username,
                        ];
                    }),
                    'total_count' => $agents->count()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get agents for date', [
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agents for date',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
