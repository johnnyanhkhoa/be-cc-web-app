<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RescheduleRequest;
use App\Http\Requests\CreateSignatureLinkRequest;
use App\Services\RescheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class RescheduleController extends Controller
{
    protected $rescheduleService;

    public function __construct(RescheduleService $rescheduleService)
    {
        $this->rescheduleService = $rescheduleService;
    }

    /**
     * Get proposed schedules by phoneCollectionId
     *
     * GET /api/reschedule/{phoneCollectionId}/proposed-schedules
     *
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function getProposedSchedules(int $phoneCollectionId): JsonResponse
    {
        try {
            Log::info('Get proposed schedules request', [
                'phone_collection_id' => $phoneCollectionId,
            ]);

            $result = $this->rescheduleService->getProposedSchedules($phoneCollectionId);

            // Return exact response from external API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            Log::error('Get proposed schedules failed', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get proposed schedules',
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Post reschedule by phoneCollectionId
     *
     * POST /api/reschedule/{phoneCollectionId}
     *
     * @param RescheduleRequest $request
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function postReschedule(RescheduleRequest $request, int $phoneCollectionId): JsonResponse
    {
        try {
            $rescheduleData = $request->validated();

            Log::info('Post reschedule request', [
                'phone_collection_id' => $phoneCollectionId,
                'reschedule_data' => $rescheduleData,
            ]);

            $result = $this->rescheduleService->postReschedule($phoneCollectionId, $rescheduleData);

            // Return exact response from external API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            Log::error('Post reschedule failed', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to post reschedule',
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get signature link by phoneCollectionId
     *
     * GET /api/reschedule/{phoneCollectionId}/signature-link
     *
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function getSignatureLink(int $phoneCollectionId): JsonResponse
    {
        try {
            Log::info('Get signature link request', [
                'phone_collection_id' => $phoneCollectionId,
            ]);

            $result = $this->rescheduleService->getSignatureLink($phoneCollectionId);

            // Return exact response from external API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            Log::error('Get signature link failed', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get signature link',
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Create new signature link by phoneCollectionId
     *
     * POST /api/reschedule/{phoneCollectionId}/signature-link
     *
     * @param CreateSignatureLinkRequest $request
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function createSignatureLink(CreateSignatureLinkRequest $request, int $phoneCollectionId): JsonResponse
    {
        try {
            $signatureData = $request->validated();

            Log::info('Create signature link request', [
                'phone_collection_id' => $phoneCollectionId,
                'signature_data' => $signatureData,
            ]);

            $result = $this->rescheduleService->createSignatureLink($phoneCollectionId, $signatureData);

            // Return exact response from external API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            Log::error('Create signature link failed', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create signature link',
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
