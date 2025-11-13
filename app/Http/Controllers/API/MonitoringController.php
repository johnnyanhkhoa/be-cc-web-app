<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcPhoneCollection;
use App\Models\TblCcPhoneCollectionDetail;
use App\Models\TblCcBatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MonitoringController extends Controller
{
    /**
     * Monitor single CCO performance
     * GET /api/monitoring/cco/{authUserId}?startDate=2025-11-07&endDate=2025-11-07
     */
    public function monitorSingleCCO(Request $request, int $authUserId): JsonResponse
    {
        try {
            // Validate dates
            $request->validate([
                'startDate' => 'required|date',
                'endDate' => 'required|date|after_or_equal:startDate',
            ]);

            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');

            // Check if user exists
            $user = User::where('authUserId', $authUserId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Get assignments in date range
           $assignments = TblCcPhoneCollection::with(['batch'])
                ->where('assignedTo', $authUserId)
                ->whereRaw(
                    'DATE("assignedAt" AT TIME ZONE \'Asia/Yangon\') BETWEEN ? AND ?',
                    [$startDate, $endDate]
                )
                ->get();

            // Get all active batches with intensity (exclude parent batches like batch 8)
            $allBatches = TblCcBatch::where('batchActive', true)
                ->whereNotNull('intensity')
                ->get();

            // Group assignments by subBatchId (not batchId)
            $assignmentsByBatch = $assignments->groupBy('subBatchId');

            // Calculate batch details for ALL batches
            $batchDetails = [];
            $totalAssigned = 0;
            $totalPending = 0;
            $totalCompleted = 0;

            foreach ($allBatches as $batch) {
                $batchId = $batch->batchId;
                $batchAssignments = $assignmentsByBatch->get($batchId, collect([]));

                $assigned = $batchAssignments->count();
                $pending = $batchAssignments->where('status', 'pending')->count();
                $completed = $batchAssignments->where('status', 'completed')->count();

                $totalAssigned += $assigned;
                $totalPending += $pending;
                $totalCompleted += $completed;

                $batchDetails[] = [
                    'batchId' => $batchId,
                    'batchCode' => $batch->code,
                    'assigned' => $assigned,
                    'pending' => $pending,
                    'completed' => $completed,
                ];
            }

            // Get all call details for this user's assignments
            $phoneCollectionIds = $assignments->pluck('phoneCollectionId')->toArray();
            $callDetails = TblCcPhoneCollectionDetail::whereIn('phoneCollectionId', $phoneCollectionIds)->get();

            // Calculate call metrics
            $totalAttempts = $callDetails->count();
            $callsByStatus = $callDetails->groupBy('callStatus')->map->count()->toArray();

            return response()->json([
                'success' => true,
                'message' => 'CCO monitoring data retrieved successfully',
                'data' => [
                    'dateRange' => [
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                    ],
                    'user' => [
                        'authUserId' => $authUserId,
                        'userFullName' => $user->userFullName,
                    ],
                    'batches' => $batchDetails,
                    'total' => [
                        'assigned' => $totalAssigned,
                        'pending' => $totalPending,
                        'completed' => $totalCompleted,
                    ],
                    'calls' => [
                        'totalAttempts' => $totalAttempts,
                        'byStatus' => $callsByStatus,
                    ],
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch CCO monitoring data', [
                'authUserId' => $authUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve monitoring data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Monitor all CCOs performance
     * GET /api/monitoring/ccos?startDate=2025-11-07&endDate=2025-11-07
     */
    public function monitorAllCCOs(Request $request): JsonResponse
    {
        try {
            // Validate dates
            $request->validate([
                'startDate' => 'required|date',
                'endDate' => 'required|date|after_or_equal:startDate',
            ]);

            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');

            // Get all assignments in date range
            $assignments = TblCcPhoneCollection::with(['batch'])
                ->whereRaw(
                    'DATE("assignedAt" AT TIME ZONE \'Asia/Yangon\') BETWEEN ? AND ?',
                    [$startDate, $endDate]
                )
                ->get();

            // Group by CCO
            $assignmentsByCCO = $assignments->groupBy('assignedTo');

            // Prepare CCO list
            $ccoList = [];
            $grandTotalAssigned = 0;
            $grandTotalPending = 0;
            $grandTotalCompleted = 0;

            // Get all active batches with intensity (exclude parent batches like batch 8)
            $allBatches = TblCcBatch::where('batchActive', true)
                ->whereNotNull('intensity')
                ->get();

            foreach ($assignmentsByCCO as $authUserId => $ccoAssignments) {
                $user = User::where('authUserId', $authUserId)->first();
                if (!$user) continue;

                // Group by subBatchId for this CCO (not batchId)
                $assignmentsByBatch = $ccoAssignments->groupBy('subBatchId');

                $batchDetails = [];
                $totalAssigned = 0;
                $totalPending = 0;
                $totalCompleted = 0;

                // Loop through ALL batches, not just batches with assignments
                foreach ($allBatches as $batch) {
                    $batchId = $batch->batchId;
                    $batchAssignments = $assignmentsByBatch->get($batchId, collect([]));

                    $assigned = $batchAssignments->count();
                    $pending = $batchAssignments->where('status', 'pending')->count();
                    $completed = $batchAssignments->where('status', 'completed')->count();

                    $totalAssigned += $assigned;
                    $totalPending += $pending;
                    $totalCompleted += $completed;

                    $batchDetails[] = [
                        'batchId' => $batchId,
                        'batchCode' => $batch->code,
                        'assigned' => $assigned,
                        'pending' => $pending,
                        'completed' => $completed,
                    ];
                }

                $grandTotalAssigned += $totalAssigned;
                $grandTotalPending += $totalPending;
                $grandTotalCompleted += $totalCompleted;

                $ccoList[] = [
                    'user' => [
                        'authUserId' => $authUserId,
                        'userFullName' => $user->userFullName,
                    ],
                    'batches' => $batchDetails,
                    'total' => [
                        'assigned' => $totalAssigned,
                        'pending' => $totalPending,
                        'completed' => $totalCompleted,
                    ],
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Team monitoring data retrieved successfully',
                'data' => [
                    'dateRange' => [
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                    ],
                    'ccos' => $ccoList,
                    'grandTotal' => [
                        'assigned' => $grandTotalAssigned,
                        'pending' => $grandTotalPending,
                        'completed' => $grandTotalCompleted,
                    ],
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch team monitoring data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve monitoring data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
