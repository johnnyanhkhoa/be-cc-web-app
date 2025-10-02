<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateCallRequest;
use App\Services\AsteriskService;
use App\Models\TblCcAsteriskCallLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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

            DB::beginTransaction();

            // Step 1: Save log to database BEFORE calling Asterisk API
            $callLog = TblCcAsteriskCallLog::create([
                'caseId' => $validated['caseId'],
                'phoneNo' => $validated['phoneNo'],
                'phoneExtension' => $validated['phoneExtension'],
                'userId' => $validated['userId'],
                'username' => $validated['username'],
                'createdBy' => $validated['userId'], // Use userId as createdBy
                'createdAt' => now(),
            ]);

            Log::info('Call log saved to database', [
                'log_id' => $callLog->id,
                'case_id' => $validated['caseId'],
                'phone_no' => $validated['phoneNo']
            ]);

            // Step 2: Call Asterisk service
            $result = $this->asteriskService->initiateCall(
                phoneExtension: $validated['phoneExtension'],
                phoneNo: $validated['phoneNo'],
                caseId: $validated['caseId'],
                username: $validated['username'],
                userId: $validated['userId']
            );

            DB::commit();

            Log::info('Voice call initiated successfully with log', [
                'log_id' => $callLog->id,
                'phone_no' => $validated['phoneNo']
            ]);

            // Return the exact response from Asterisk API
            return response()->json(
                $result['data'],
                $result['status_code']
            );

        } catch (Exception $e) {
            DB::rollBack();

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

    /**
     * Get call logs with filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCallLogs(Request $request): JsonResponse
    {
        try {
            $query = TblCcAsteriskCallLog::query();

            // Filter by caseId
            if ($request->has('caseId') && !empty($request->caseId)) {
                $query->where('caseId', $request->caseId);
            }

            // Filter by userId
            if ($request->has('userId') && !empty($request->userId)) {
                $query->where('userId', $request->userId);
            }

            // Filter by phoneNo
            if ($request->has('phoneNo') && !empty($request->phoneNo)) {
                $query->where('phoneNo', 'LIKE', '%' . $request->phoneNo . '%');
            }

            // Filter by date range
            if ($request->has('fromDate') && !empty($request->fromDate)) {
                $query->whereDate('createdAt', '>=', $request->fromDate);
            }

            if ($request->has('toDate') && !empty($request->toDate)) {
                $query->whereDate('createdAt', '<=', $request->toDate);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'createdAt');
            $sortOrder = $request->get('sort_order', 'desc');

            $allowedSortFields = ['id', 'caseId', 'phoneNo', 'userId', 'createdAt'];

            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'createdAt';
            }

            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 50);
            if ($perPage > 100) {
                $perPage = 100;
            }

            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Call logs retrieved successfully',
                'data' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve call logs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve call logs',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
