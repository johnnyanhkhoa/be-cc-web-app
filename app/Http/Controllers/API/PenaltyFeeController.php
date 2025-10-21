<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExemptPenaltyFeeRequest;
use App\Services\PenaltyFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class PenaltyFeeController extends Controller
{
    protected $penaltyFeeService;

    public function __construct(PenaltyFeeService $penaltyFeeService)
    {
        $this->penaltyFeeService = $penaltyFeeService;
    }

    /**
     * Get penalty fee info by phoneCollectionId
     *
     * GET /api/penalty-fee/{phoneCollectionId}
     *
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function getPenaltyFeeInfo(int $phoneCollectionId): JsonResponse
    {
        try {
            Log::info('Get penalty fee info request', [
                'phone_collection_id' => $phoneCollectionId,
            ]);

            $result = $this->penaltyFeeService->getPenaltyFeeInfo($phoneCollectionId);

            // Return exact response from external API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            Log::error('Get penalty fee info failed', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get penalty fee info',
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Exempt penalty fee by phoneCollectionId
     *
     * POST /api/penalty-fee/{phoneCollectionId}/exempt
     *
     * @param ExemptPenaltyFeeRequest $request
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function exemptPenaltyFee(ExemptPenaltyFeeRequest $request, int $phoneCollectionId): JsonResponse
    {
        try {
            $reasonExempted = $request->validated()['reasonExempted'];

            Log::info('Exempt penalty fee request', [
                'phone_collection_id' => $phoneCollectionId,
                'reason' => $reasonExempted,
            ]);

            $result = $this->penaltyFeeService->exemptPenaltyFee($phoneCollectionId, $reasonExempted);

            // Return exact response from external API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            Log::error('Exempt penalty fee failed', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to exempt penalty fee',
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Transfer contract to litigation by phoneCollectionId
     *
     * POST /api/contracts/{phoneCollectionId}/transfer-litigation
     *
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function transferToLitigation(int $phoneCollectionId): JsonResponse
    {
        try {
            Log::info('Transfer to litigation request', [
                'phone_collection_id' => $phoneCollectionId,
            ]);

            $result = $this->penaltyFeeService->transferToLitigation($phoneCollectionId);

            // Return exact response from external API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            Log::error('Transfer to litigation failed', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer contract to litigation',
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
