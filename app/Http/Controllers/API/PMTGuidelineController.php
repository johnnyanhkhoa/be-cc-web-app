<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcPMTGuideline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class PMTGuidelineController extends Controller
{
    /**
     * Get payment guideline by pmtName
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getByName(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'pmtName' => ['required', 'string', 'max:255'],
            ]);

            $pmtName = $request->input('pmtName');

            Log::info('Getting payment guideline by name', [
                'pmt_name' => $pmtName
            ]);

            // Try exact match first
            $guideline = TblCcPMTGuideline::byName($pmtName)->first();

            if (!$guideline) {
                // Try partial match if exact match fails
                $guideline = TblCcPMTGuideline::byNameLike($pmtName)->first();
            }

            if (!$guideline) {
                Log::warning('Payment guideline not found', [
                    'pmt_name' => $pmtName
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment guideline not found',
                    'error' => 'No payment guideline found with the specified name'
                ], 404);
            }

            Log::info('Payment guideline found successfully', [
                'pmt_id' => $guideline->pmtId,
                'pmt_name' => $guideline->pmtName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment guideline retrieved successfully',
                'data' => [
                    'pmtId' => $guideline->pmtId,
                    'pmtName' => $guideline->pmtName,
                    'pmtStep' => $guideline->pmtStep,
                    'pmtRemark' => $guideline->pmtRemark,
                    'formattedSteps' => $guideline->getFormattedSteps(),
                    'createdAt' => $guideline->createdAt?->format('Y-m-d H:i:s'),
                    'updatedAt' => $guideline->updatedAt?->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get payment guideline by name', [
                'pmt_name' => $request->input('pmtName'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment guideline',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all available payment guidelines with full details
     *
     * @return JsonResponse
     */
    public function getAll(): JsonResponse
    {
        try {
            $guidelines = TblCcPMTGuideline::orderBy('pmtName')->get();

            $transformedGuidelines = $guidelines->map(function ($guideline) {
                return [
                    'pmtId' => $guideline->pmtId,
                    'pmtName' => $guideline->pmtName,
                    'pmtStep' => $guideline->pmtStep,
                    'pmtRemark' => $guideline->pmtRemark,
                    'formattedSteps' => $guideline->getFormattedSteps(),
                    'stepCount' => count($guideline->getStepsArray()),
                    'createdAt' => $guideline->createdAt?->format('Y-m-d H:i:s'),
                    'updatedAt' => $guideline->updatedAt?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'All payment guidelines retrieved successfully',
                'data' => $transformedGuidelines,
                'total' => $guidelines->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get all payment guidelines', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment guidelines',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get payment guideline by pmtId
     *
     * @param Request $request
     * @param int $pmtId
     * @return JsonResponse
     */
    public function getById(Request $request, int $pmtId): JsonResponse
    {
        try {
            if ($pmtId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment guideline ID',
                    'error' => 'Payment guideline ID must be a positive integer'
                ], 400);
            }

            $guideline = TblCcPMTGuideline::find($pmtId);

            if (!$guideline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment guideline not found',
                    'error' => 'The specified payment guideline does not exist'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment guideline retrieved successfully',
                'data' => [
                    'pmtId' => $guideline->pmtId,
                    'pmtName' => $guideline->pmtName,
                    'pmtStep' => $guideline->pmtStep,
                    'pmtRemark' => $guideline->pmtRemark,
                    'formattedSteps' => $guideline->getFormattedSteps(),
                    'createdAt' => $guideline->createdAt?->format('Y-m-d H:i:s'),
                    'updatedAt' => $guideline->updatedAt?->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get payment guideline by ID', [
                'pmt_id' => $pmtId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment guideline',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Search payment guidelines by partial name
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => ['required', 'string', 'min:1', 'max:255'],
            ]);

            $searchTerm = $request->input('q');

            $guidelines = TblCcPMTGuideline::byNameLike($searchTerm)
                ->orderBy('pmtName')
                ->get();

            $transformedGuidelines = $guidelines->map(function ($guideline) {
                return [
                    'pmtId' => $guideline->pmtId,
                    'pmtName' => $guideline->pmtName,
                    'pmtRemark' => $guideline->pmtRemark,
                    'stepCount' => count($guideline->getStepsArray()),
                    'createdAt' => $guideline->createdAt?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment guidelines search completed',
                'data' => $transformedGuidelines,
                'total' => $guidelines->count(),
                'searchTerm' => $searchTerm
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to search payment guidelines', [
                'search_term' => $request->input('q'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search payment guidelines',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
