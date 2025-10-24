<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcBatch;
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
}
