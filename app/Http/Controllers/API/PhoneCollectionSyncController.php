<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PhoneCollectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class PhoneCollectionSyncController extends Controller
{
    protected $syncService;

    public function __construct(PhoneCollectionSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Manually trigger phone collection sync
     */
    public function syncPhoneCollections(): JsonResponse
    {
        try {
            Log::info('Manual phone collection sync triggered');

            $results = $this->syncService->syncPhoneCollections();

            return response()->json([
                'success' => true,
                'message' => 'Phone collection sync completed successfully',
                'data' => $results
            ], 200);

        } catch (Exception $e) {
            Log::error('Manual phone collection sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Phone collection sync failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
