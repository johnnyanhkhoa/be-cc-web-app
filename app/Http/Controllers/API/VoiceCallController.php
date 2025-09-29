<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateCallRequest;
use App\Services\AsteriskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class VoiceCallController extends Controller
{
    protected $asteriskService;

    public function __construct(AsteriskService $asteriskService)
    {
        $this->asteriskService = $asteriskService;
    }

    /**
     * Initiate a voice call through Asterisk
     *
     * @param InitiateCallRequest $request
     * @return JsonResponse
     */
    public function initiateCall(InitiateCallRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            Log::info('Voice call request received', [
                'phone_extension' => $validated['phoneExtension'],
                'phone_no' => $validated['phoneNo'],
                'case_id' => $validated['caseId'],
                'username' => $validated['username'],
                'user_id' => $validated['userId'],
            ]);

            // Call Asterisk service
            $result = $this->asteriskService->initiateCall(
                phoneExtension: $validated['phoneExtension'],
                phoneNo: $validated['phoneNo'],
                caseId: $validated['caseId'],
                username: $validated['username'],
                userId: $validated['userId']
            );

            // Return the exact response from Asterisk API
            return response()->json(
                $result['data'],
                $result['status_code']
            );

        } catch (Exception $e) {
            Log::error('Voice call initiation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Determine status code
            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = 500;
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate voice call',
                'error' => config('app.debug') ? $e->getMessage() : 'Unable to process voice call request'
            ], $statusCode);
        }
    }

    /**
     * Get call status by call ID (optional endpoint)
     *
     * @param string $callId
     * @return JsonResponse
     */
    public function getCallStatus(string $callId): JsonResponse
    {
        try {
            $result = $this->asteriskService->getCallStatus($callId);

            return response()->json($result['data'], 200);

        } catch (Exception $e) {
            Log::error('Failed to get call status', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get call status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
