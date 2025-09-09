<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class TblCcReasonController extends Controller
{
    /**
     * Display a listing of all active reasons
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Get all active reasons, sorted by name
            $reasons = TblCcReason::where('reasonActive', true)
                                 ->orderBy('reasonName')
                                 ->get();

            return response()->json([
                'success' => true,
                'message' => 'Reasons retrieved successfully',
                'data' => $reasons,
                'total' => $reasons->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve reasons', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reasons',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified reason by ID
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $reason = TblCcReason::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Reason retrieved successfully',
                'data' => $reason
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve reason', [
                'reasonId' => $id,
                'error' => $e->getMessage()
            ]);

            $statusCode = $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500;

            return response()->json([
                'success' => false,
                'message' => $statusCode === 404 ? 'Reason not found' : 'Failed to retrieve reason',
                'error' => $statusCode === 404 ? 'The requested reason does not exist' : 'Internal server error'
            ], $statusCode);
        }
    }

    /**
     * Get reasons grouped by type (simplified)
     *
     * @return JsonResponse
     */
    public function getByType(): JsonResponse
    {
        try {
            $reasons = TblCcReason::where('reasonActive', true)
                                 ->orderBy('reasonType')
                                 ->orderBy('reasonName')
                                 ->get()
                                 ->groupBy('reasonType');

            return response()->json([
                'success' => true,
                'message' => 'Reasons grouped by type retrieved successfully',
                'data' => $reasons,
                'total_types' => $reasons->count(),
                'total_reasons' => $reasons->flatten()->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get reasons by type', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reasons by type',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all reasons (including inactive)
     *
     * @return JsonResponse
     */
    public function all(): JsonResponse
    {
        try {
            $reasons = TblCcReason::orderBy('reasonType')
                                 ->orderBy('reasonName')
                                 ->get();

            $active = $reasons->where('reasonActive', true);
            $inactive = $reasons->where('reasonActive', false);

            return response()->json([
                'success' => true,
                'message' => 'All reasons retrieved successfully',
                'data' => $reasons,
                'summary' => [
                    'total' => $reasons->count(),
                    'active' => $active->count(),
                    'inactive' => $inactive->count()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve all reasons', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve all reasons',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get simple statistics
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        try {
            $total = TblCcReason::count();
            $active = TblCcReason::where('reasonActive', true)->count();
            $inactive = TblCcReason::where('reasonActive', false)->count();

            $byType = TblCcReason::select('reasonType')
                                ->selectRaw('COUNT(*) as count')
                                ->groupBy('reasonType')
                                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'total_reasons' => $total,
                    'active_reasons' => $active,
                    'inactive_reasons' => $inactive,
                    'by_type' => $byType
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
