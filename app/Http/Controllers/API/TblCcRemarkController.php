<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcRemark;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class TblCcRemarkController extends Controller
{
    /**
     * Display a listing of all active remarks
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Get all active remarks, sorted by contact type then content
            $remarks = TblCcRemark::where('remarkActive', true)
                                 ->orderBy('contactType')
                                 ->orderBy('remarkContent')
                                 ->get();

            return response()->json([
                'success' => true,
                'message' => 'Remarks retrieved successfully',
                'data' => $remarks,
                'total' => $remarks->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve remarks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve remarks',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified remark by ID
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $remark = TblCcRemark::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Remark retrieved successfully',
                'data' => $remark
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve remark', [
                'remarkId' => $id,
                'error' => $e->getMessage()
            ]);

            $statusCode = $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500;

            return response()->json([
                'success' => false,
                'message' => $statusCode === 404 ? 'Remark not found' : 'Failed to retrieve remark',
                'error' => $statusCode === 404 ? 'The requested remark does not exist' : 'Internal server error'
            ], $statusCode);
        }
    }

    /**
     * Get remarks grouped by contact type
     *
     * @return JsonResponse
     */
    public function getByContactType(): JsonResponse
    {
        try {
            $remarks = TblCcRemark::where('remarkActive', true)
                                 ->orderBy('contactType')
                                 ->orderBy('remarkContent')
                                 ->get()
                                 ->groupBy('contactType');

            return response()->json([
                'success' => true,
                'message' => 'Remarks grouped by contact type retrieved successfully',
                'data' => $remarks,
                'summary' => [
                    'total_types' => $remarks->count(),
                    'total_remarks' => $remarks->flatten()->count(),
                    'types' => $remarks->keys()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get remarks by contact type', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve remarks by contact type',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get remarks for specific contact type
     *
     * @param string $contactType
     * @return JsonResponse
     */
    public function getByType(string $contactType): JsonResponse
    {
        try {
            // Validate contact type
            if (!in_array($contactType, TblCcRemark::getContactTypes())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid contact type',
                    'error' => 'Contact type must be one of: ' . implode(', ', TblCcRemark::getContactTypes())
                ], 400);
            }

            $remarks = TblCcRemark::where('remarkActive', true)
                                 ->where('contactType', $contactType)
                                 ->orderBy('remarkContent')
                                 ->get();

            return response()->json([
                'success' => true,
                'message' => "Remarks for contact type '{$contactType}' retrieved successfully",
                'data' => $remarks,
                'contact_type' => $contactType,
                'total' => $remarks->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get remarks by specific contact type', [
                'contactType' => $contactType,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve remarks for contact type',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all contact types with counts
     *
     * @return JsonResponse
     */
    public function getContactTypes(): JsonResponse
    {
        try {
            $types = TblCcRemark::select('contactType')
                               ->selectRaw('COUNT(*) as total_remarks')
                               ->selectRaw('SUM(CASE WHEN remarkActive = true THEN 1 ELSE 0 END) as active_remarks')
                               ->groupBy('contactType')
                               ->orderBy('contactType')
                               ->get();

            return response()->json([
                'success' => true,
                'message' => 'Contact types retrieved successfully',
                'data' => $types,
                'available_types' => TblCcRemark::getContactTypes(),
                'summary' => [
                    'total_types' => $types->count(),
                    'total_remarks' => $types->sum('total_remarks'),
                    'total_active_remarks' => $types->sum('active_remarks')
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get contact types', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contact types',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all remarks (including inactive)
     *
     * @return JsonResponse
     */
    public function all(): JsonResponse
    {
        try {
            $remarks = TblCcRemark::orderBy('contactType')
                                 ->orderBy('remarkContent')
                                 ->get();

            $active = $remarks->where('remarkActive', true);
            $inactive = $remarks->where('remarkActive', false);

            return response()->json([
                'success' => true,
                'message' => 'All remarks retrieved successfully',
                'data' => $remarks,
                'summary' => [
                    'total' => $remarks->count(),
                    'active' => $active->count(),
                    'inactive' => $inactive->count(),
                    'by_contact_type' => $remarks->groupBy('contactType')->map->count()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve all remarks', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve all remarks',
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
            $total = TblCcRemark::count();
            $active = TblCcRemark::where('remarkActive', true)->count();
            $inactive = TblCcRemark::where('remarkActive', false)->count();

            $byContactType = TblCcRemark::select('contactType')
                                      ->selectRaw('COUNT(*) as count')
                                      ->selectRaw('SUM(CASE WHEN remarkActive = true THEN 1 ELSE 0 END) as active_count')
                                      ->groupBy('contactType')
                                      ->orderBy('contactType')
                                      ->get();

            return response()->json([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'total_remarks' => $total,
                    'active_remarks' => $active,
                    'inactive_remarks' => $inactive,
                    'by_contact_type' => $byContactType,
                    'available_contact_types' => TblCcRemark::getContactTypes()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get remark statistics', [
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
