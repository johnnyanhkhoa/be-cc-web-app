<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CallController extends Controller
{
    /**
     * Get all calls
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $query = Call::with(['assignedAgent', 'assignedByUser', 'creator']);

            // Get all results
            $calls = $query->orderBy('created_at', 'desc')->get();

            // Transform data
            $transformedCalls = $calls->map(function ($call) {
                return [
                    'id' => $call->id,
                    'call_id' => $call->call_id,
                    'status' => $call->status,
                    'status_label' => $call->getStatusLabel(),
                    'assigned_to' => $call->assignedAgent ? [
                        'id' => $call->assignedAgent->id,
                        'name' => $call->assignedAgent->user_full_name,
                        'email' => $call->assignedAgent->email,
                    ] : null,
                    'assigned_by' => $call->assignedByUser ? [
                        'id' => $call->assignedByUser->id,
                        'name' => $call->assignedByUser->user_full_name,
                    ] : null,
                    'assigned_at' => $call->assigned_at?->format('Y-m-d H:i:s'),
                    'total_attempts' => $call->total_attempts,
                    'last_attempt_at' => $call->last_attempt_at?->format('Y-m-d H:i:s'),
                    'created_at' => $call->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'All calls retrieved successfully',
                'data' => [
                    'calls' => $transformedCalls,
                    'total_count' => $calls->count(),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get all calls', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve calls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assigned calls for specific user
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function getAssignedCallsForUser(int $userId): JsonResponse
    {
        try {
            // Validate user exists
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => 'The specified user does not exist'
                ], 404);
            }

            $query = Call::with(['assignedAgent', 'assignedByUser', 'creator'])
                ->assignedTo($userId);

            // Get assigned calls for user
            $calls = $query->orderBy('assigned_at', 'desc')->get();

            // Transform data
            $transformedCalls = $calls->map(function ($call) {
                return [
                    'id' => $call->id,
                    'call_id' => $call->call_id,
                    'status' => $call->status,
                    'status_label' => $call->getStatusLabel(),
                    'assigned_by' => $call->assignedByUser ? [
                        'id' => $call->assignedByUser->id,
                        'name' => $call->assignedByUser->user_full_name,
                    ] : null,
                    'assigned_at' => $call->assigned_at?->format('Y-m-d H:i:s'),
                    'total_attempts' => $call->total_attempts,
                    'last_attempt_at' => $call->last_attempt_at?->format('Y-m-d H:i:s'),
                    'created_at' => $call->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Assigned calls retrieved successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->user_full_name,
                        'email' => $user->email,
                    ],
                    'calls' => $transformedCalls,
                    'total_count' => $calls->count(),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get assigned calls for user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assigned calls',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
