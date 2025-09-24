<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetScriptsRequest;
use App\Models\TblCcBatch;
use App\Models\TblCcScript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class TblCcScriptController extends Controller
{
    /**
     * Get scripts based on batchId and daysPastDue
     *
     * @param GetScriptsRequest $request
     * @return JsonResponse
     */
    public function getScripts(GetScriptsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $batchId = $validated['batchId'];
            $daysPastDue = $validated['daysPastDue'];

            Log::info('Getting scripts for batch and days past due', [
                'batch_id' => $batchId,
                'days_past_due' => $daysPastDue
            ]);

            // Step 1: Get batch with scriptCollectionId
            $batch = TblCcBatch::find($batchId);

            if (!$batch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch not found',
                    'error' => 'The specified batch does not exist'
                ], 404);
            }

            // Check if batch is active
            if (!$batch->batchActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch is not active',
                    'error' => 'The specified batch is deactivated'
                ], 400);
            }

            // Step 2: Parse scriptCollectionId JSON
            $scriptCollectionId = $batch->scriptCollectionId;

            if (!$scriptCollectionId || !isset($scriptCollectionId['scriptId']) || !is_array($scriptCollectionId['scriptId'])) {
                Log::warning('Invalid or empty scriptCollectionId', [
                    'batch_id' => $batchId,
                    'script_collection_id' => $scriptCollectionId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No script collection found for this batch',
                    'error' => 'Batch does not have valid script collection data'
                ], 400);
            }

            $scriptIds = $scriptCollectionId['scriptId'];

            Log::info('Found script IDs in batch', [
                'batch_id' => $batchId,
                'script_ids' => $scriptIds,
                'script_count' => count($scriptIds)
            ]);

            // Step 3: Get scripts that match the criteria
            $matchingScripts = TblCcScript::whereIn('scriptId', $scriptIds)
                ->where('scriptActive', true)
                ->where(function ($query) use ($daysPastDue) {
                    $query->where(function ($q) use ($daysPastDue) {
                        // Check if daysPastDue falls within the range
                        $q->where('daysPastDueFrom', '<=', $daysPastDue)
                          ->where('daysPastDueTo', '>=', $daysPastDue);
                    })
                    ->orWhere(function ($q) use ($daysPastDue) {
                        // Handle cases where daysPastDueTo might be null (open-ended range)
                        $q->where('daysPastDueFrom', '<=', $daysPastDue)
                          ->whereNull('daysPastDueTo');
                    });
                })
                ->orderBy('daysPastDueFrom', 'asc')
                ->orderBy('scriptId', 'asc')
                ->get();

            Log::info('Found matching scripts', [
                'batch_id' => $batchId,
                'days_past_due' => $daysPastDue,
                'matching_scripts_count' => $matchingScripts->count(),
                'matching_script_ids' => $matchingScripts->pluck('scriptId')->toArray()
            ]);

            // Step 4: Transform data for response
            $transformedScripts = $matchingScripts->map(function ($script) {
                return [
                    'scriptId' => $script->scriptId,
                    'receiver' => $script->receiver,
                    'daysPastDueFrom' => $script->daysPastDueFrom,
                    'daysPastDueTo' => $script->daysPastDueTo,
                    'scriptContentBur' => $script->scriptContentBur,
                    'scriptContentEng' => $script->scriptContentEng,
                    'scriptRemark' => $script->scriptRemark,
                    'scriptActive' => $script->scriptActive,
                    'createdAt' => $script->createdAt?->format('Y-m-d H:i:s'),
                    'updatedAt' => $script->updatedAt?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Scripts retrieved successfully',
                'data' => [
                    'batch' => [
                        'batchId' => $batch->batchId,
                        'type' => $batch->type,
                        'code' => $batch->code,
                        'segmentType' => $batch->segmentType,
                        'batchActive' => $batch->batchActive,
                        'scriptCollectionId' => $batch->scriptCollectionId,
                    ],
                    'criteria' => [
                        'batchId' => $batchId,
                        'daysPastDue' => $daysPastDue,
                        'availableScriptIds' => $scriptIds,
                    ],
                    'scripts' => $transformedScripts,
                    'summary' => [
                        'totalScriptsInBatch' => count($scriptIds),
                        'matchingScripts' => $matchingScripts->count(),
                        'matchingScriptIds' => $matchingScripts->pluck('scriptId')->toArray(),
                        'receivers' => $matchingScripts->pluck('receiver')->unique()->values(),
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get scripts', [
                'batch_id' => $batchId ?? null,  // FIX: Use từ validated data
                'days_past_due' => $daysPastDue ?? null,  // FIX: Use từ validated data
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scripts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all active batches with script collections
     *
     * @return JsonResponse
     */
    public function getBatchesWithScripts(): JsonResponse
    {
        try {
            $batches = TblCcBatch::where('batchActive', true)
                ->whereNotNull('scriptCollectionId')
                ->orderBy('segmentType')
                ->orderBy('code')
                ->get();

            $transformedBatches = $batches->map(function ($batch) {
                $scriptIds = [];
                if ($batch->scriptCollectionId && isset($batch->scriptCollectionId['scriptId'])) {
                    $scriptIds = $batch->scriptCollectionId['scriptId'];
                }

                return [
                    'batchId' => $batch->batchId,
                    'type' => $batch->type,
                    'code' => $batch->code,
                    'segmentType' => $batch->segmentType,
                    'batchActive' => $batch->batchActive,
                    'scriptIds' => $scriptIds,
                    'scriptCount' => count($scriptIds),
                    'createdAt' => $batch->createdAt?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Active batches with scripts retrieved successfully',
                'data' => $transformedBatches,
                'total' => $batches->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get batches with scripts', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batches with scripts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get script details by scriptId
     *
     * @param Request $request
     * @param int $scriptId
     * @return JsonResponse
     */
    public function getScriptById(Request $request, int $scriptId): JsonResponse
    {
        try {
            if ($scriptId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid script ID',
                    'error' => 'Script ID must be a positive integer'
                ], 400);
            }

            $script = TblCcScript::find($scriptId);

            if (!$script) {
                return response()->json([
                    'success' => false,
                    'message' => 'Script not found',
                    'error' => 'The specified script does not exist'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Script retrieved successfully',
                'data' => [
                    'scriptId' => $script->scriptId,
                    'receiver' => $script->receiver,
                    'daysPastDueFrom' => $script->daysPastDueFrom,
                    'daysPastDueTo' => $script->daysPastDueTo,
                    'scriptContentBur' => $script->scriptContentBur,
                    'scriptContentEng' => $script->scriptContentEng,
                    'scriptRemark' => $script->scriptRemark,
                    'scriptActive' => $script->scriptActive,
                    'dtDeactivated' => $script->dtDeactivated?->format('Y-m-d H:i:s'),
                    'createdAt' => $script->createdAt?->format('Y-m-d H:i:s'),
                    'updatedAt' => $script->updatedAt?->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get script by ID', [
                'script_id' => $scriptId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve script',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
