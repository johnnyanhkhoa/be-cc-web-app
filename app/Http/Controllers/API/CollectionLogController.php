<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetCollectionLogsRequest;
use App\Services\CollectionLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class CollectionLogController extends Controller
{
    protected $collectionLogService;

    public function __construct(CollectionLogService $collectionLogService)
    {
        $this->collectionLogService = $collectionLogService;
    }

    /**
     * Get unified collection logs (Phone Collection + Litigation)
     *
     * @param GetCollectionLogsRequest $request
     * @param int $contractId
     * @return JsonResponse
     */
    public function getCollectionLogs(GetCollectionLogsRequest $request, int $contractId): JsonResponse
    {
        try {
            $validated = $request->validated();

            Log::info('Collection logs request received', [
                'contract_id' => $contractId,
                'from' => $validated['from'],
                'to' => $validated['to'],
            ]);

            $result = $this->collectionLogService->getUnifiedLogs(
                $contractId,
                $validated['from'],
                $validated['to']
            );

            Log::info('Collection logs retrieved successfully', [
                'contract_id' => $contractId,
                'total_logs' => $result['total'],
                'phone_collection_count' => $result['phoneCollectionCount'],
                'litigation_count' => $result['litigationCount'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Collection logs retrieved successfully',
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve collection logs', [
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve collection logs',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
