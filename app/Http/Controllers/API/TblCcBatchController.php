<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\TblCcBatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class TblCcBatchController extends Controller
{
    /**
     * Get all batches
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Query batches
            $query = TblCcBatch::query();

            // Optional: Filter by batchActive if provided
            if ($request->has('batchActive')) {
                $query->where('batchActive', filter_var($request->batchActive, FILTER_VALIDATE_BOOLEAN));
            }

            // Optional: Filter by segmentType if provided
            if ($request->has('segmentType') && !empty($request->segmentType)) {
                $query->where('segmentType', $request->segmentType);
            }

            // Get results ordered by batchId
            $batches = $query->orderBy('batchId', 'asc')->get();

            // Transform data
            $transformedData = $batches->map(function ($batch) {
                return [
                    'batchId' => $batch->batchId,
                    'type' => $batch->type,
                    'code' => $batch->code,
                    'batchName' => $batch->batchName,
                    'intensity' => $batch->intensity,
                    'batchActive' => $batch->batchActive,
                    'segmentType' => $batch->segmentType,
                ];
            });

            Log::info('Batches retrieved successfully', [
                'count' => $batches->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batches retrieved successfully',
                'data' => $transformedData,
                'total' => $batches->count(),
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve batches', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batches',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get batch by ID
     *
     * @param int $batchId
     * @return JsonResponse
     */
    public function show(int $batchId): JsonResponse
    {
        try {
            $batch = TblCcBatch::find($batchId);

            if (!$batch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch not found',
                    'error' => "Batch with ID {$batchId} does not exist"
                ], 404);
            }

            Log::info('Batch retrieved successfully', [
                'batch_id' => $batchId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch retrieved successfully',
                'data' => [
                    'batchId' => $batch->batchId,
                    'type' => $batch->type,
                    'code' => $batch->code,
                    'batchName' => $batch->batchName,
                    'intensity' => $batch->intensity,
                    'batchActive' => $batch->batchActive,
                    'segmentType' => $batch->segmentType,
                    'scriptCollectionId' => $batch->scriptCollectionId,
                    'deactivatedAt' => $batch->deactivatedAt?->format('Y-m-d H:i:s'),
                    'deactivatedBy' => $batch->deactivatedBy,
                    'createdAt' => $batch->createdAt?->format('Y-m-d H:i:s'),
                    'createdBy' => $batch->createdBy,
                    'updatedAt' => $batch->updatedAt?->format('Y-m-d H:i:s'),
                    'updatedBy' => $batch->updatedBy,
                ],
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batch',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update batch
     *
     * @param UpdateBatchRequest $request
     * @return JsonResponse
     */
    public function update(UpdateBatchRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $batchId = $validated['batchId'];
            $updatedByAuthUserId = $validated['updatedBy'];

            Log::info('Updating batch', [
                'batch_id' => $batchId,
                'updated_by_auth_user_id' => $updatedByAuthUserId,
            ]);

            // Find batch
            $batch = TblCcBatch::find($batchId);

            if (!$batch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch not found',
                    'error' => "Batch with ID {$batchId} does not exist"
                ], 404);
            }

            // Verify updatedBy user exists
            $updatedByUser = User::where('authUserId', $updatedByAuthUserId)->first();

            if (!$updatedByUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => "User with authUserId {$updatedByAuthUserId} does not exist"
                ], 404);
            }

            // Prepare update data (only update fields that are provided)
            $updateData = [
                'intensity' => json_decode($validated['intensity'], true), // Convert JSON string to array
                'updatedBy' => $updatedByAuthUserId, // Store authUserId
                'updatedAt' => now()->timezone('Asia/Yangon'), // Myanmar time
            ];

            // Only update if provided (not null)
            if (isset($validated['batchActive'])) {
                $updateData['batchActive'] = $validated['batchActive'];
            }

            if (isset($validated['deactivatedAt'])) {
                $updateData['deactivatedAt'] = $validated['deactivatedAt'];
            }

            if (isset($validated['deactivatedBy'])) {
                $updateData['deactivatedBy'] = $validated['deactivatedBy'];
            }

            // Store old values for logging
            $oldValues = [
                'intensity' => $batch->intensity,
                'batchActive' => $batch->batchActive,
                'deactivatedAt' => $batch->deactivatedAt,
                'deactivatedBy' => $batch->deactivatedBy,
            ];

            // Update batch
            $batch->update($updateData);

            Log::info('Batch updated successfully', [
                'batch_id' => $batchId,
                'updated_by_auth_user_id' => $updatedByAuthUserId,
                'old_values' => $oldValues,
                'new_values' => $updateData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch updated successfully',
                'data' => [
                    'batchId' => $batch->batchId,
                    'type' => $batch->type,
                    'code' => $batch->code,
                    'batchName' => $batch->batchName,
                    'intensity' => $batch->intensity,
                    'batchActive' => $batch->batchActive,
                    'segmentType' => $batch->segmentType,
                    'scriptCollectionId' => $batch->scriptCollectionId,
                    'deactivatedAt' => $batch->deactivatedAt?->format('Y-m-d H:i:s'),
                    'deactivatedBy' => $batch->deactivatedBy,
                    'updatedAt' => $batch->updatedAt?->format('Y-m-d H:i:s'),
                    'updatedBy' => $batch->updatedBy,
                ],
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to update batch', [
                'batch_id' => $validated['batchId'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update batch',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
