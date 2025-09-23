<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCcPhoneCollectionDetailRequest;
use App\Models\TblCcPhoneCollectionDetail;
use App\Models\TblCcRemark;
use App\Models\TblCcCaseResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class TblCcPhoneCollectionDetailController extends Controller
{
    /**
     * Create a new phone collection detail record
     *
     * @param CreateCcPhoneCollectionDetailRequest $request
     * @return JsonResponse
     */
    public function store(CreateCcPhoneCollectionDetailRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            Log::info('Creating phone collection detail', [
                'contact_type' => $validatedData['contactType'] ?? null,
                'call_status' => $validatedData['callStatus'] ?? null,
                'createdBy' => $validatedData['createdBy']
            ]);

            // Create the record
            $phoneCollectionDetail = TblCcPhoneCollectionDetail::create($validatedData);

            Log::info('Phone collection detail created successfully', [
                'phoneCollectionDetailId' => $phoneCollectionDetail->phoneCollectionDetailId,
                'contact_type' => $phoneCollectionDetail->contactType,
                'call_status' => $phoneCollectionDetail->callStatus
            ]);

            // Load relationships for response
            $phoneCollectionDetail->load(['standardRemark', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Phone collection detail created successfully',
                'data' => [
                    'id' => $phoneCollectionDetail->id,
                    'contactType' => $phoneCollectionDetail->contactType,
                    'phoneId' => $phoneCollectionDetail->phoneId,
                    'contactDetailId' => $phoneCollectionDetail->contactDetailId,
                    'contactPhoneNumer' => $phoneCollectionDetail->contactPhoneNumer,
                    'contactName' => $phoneCollectionDetail->contactName,
                    'contactRelation' => $phoneCollectionDetail->contactRelation,
                    'callStatus' => $phoneCollectionDetail->callStatus,
                    'callResultId' => $phoneCollectionDetail->callResultId,
                    'leaveMessage' => $phoneCollectionDetail->leaveMessage,
                    'remark' => $phoneCollectionDetail->remark,
                    'promisedPaymentDate' => $phoneCollectionDetail->promisedPaymentDate?->format('Y-m-d'),
                    'askingPostponePayment' => $phoneCollectionDetail->askingPostponePayment,
                    'dtCallLater' => $phoneCollectionDetail->dtCallLater?->format('Y-m-d'),
                    'dtCallStarted' => $phoneCollectionDetail->dtCallStarted?->format('Y-m-d H:i:s'),
                    'dtCallEnded' => $phoneCollectionDetail->dtCallEnded?->format('Y-m-d H:i:s'),
                    'updatePhoneRequest' => $phoneCollectionDetail->updatePhoneRequest,
                    'updatePhoneRemark' => $phoneCollectionDetail->updatePhoneRemark,
                    'standardRemarkId' => $phoneCollectionDetail->standardRemarkId,
                    'standardRemarkContent' => $phoneCollectionDetail->standardRemarkContent,
                    'reschedulingEvidence' => $phoneCollectionDetail->reschedulingEvidence,
                    'uploadDocuments' => $phoneCollectionDetail->uploadDocuments,
                    'createdAt' => $phoneCollectionDetail->createdAt?->format('Y-m-d H:i:s'),
                    'createdBy' => $phoneCollectionDetail->createdBy,
                    // Include related data if available
                    'standardRemark' => $phoneCollectionDetail->standardRemark,
                    'creator' => $phoneCollectionDetail->creator ? [
                        'id' => $phoneCollectionDetail->creator->id,
                        'username' => $phoneCollectionDetail->creator->username,
                        'email' => $phoneCollectionDetail->creator->email,
                    ] : null,
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to create phone collection detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated() // Thay đổi từ all() thành validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create phone collection detail',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all case results for dropdown
     *
     * @return JsonResponse
     */
    public function getCaseResults(): JsonResponse
    {
        try {
            $caseResults = TblCcCaseResult::active()
                                        ->orderBy('caseResultName')
                                        ->get(['caseResultId', 'caseResultName', 'escalationRemark', 'caseResultRemark', 'preDue', 'pastDue', 'escalation', 'specialCase', 'dslp']);

            return response()->json([
                'success' => true,
                'message' => 'Case results retrieved successfully',
                'data' => $caseResults,
                'total' => $caseResults->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get case results', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case results',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all standard remarks for dropdown
     *
     * @return JsonResponse
     */
    public function getStandardRemarks(): JsonResponse
    {
        try {
            $remarks = TblCcRemark::where('remarkActive', true)
                                 ->orderBy('contactType')
                                 ->orderBy('remarkContent')
                                 ->get(['remarkId', 'remarkContent', 'contactType']);

            return response()->json([
                'success' => true,
                'message' => 'Standard remarks retrieved successfully',
                'data' => $remarks,
                'total' => $remarks->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get standard remarks', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve standard remarks',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get metadata for form (contact types, call statuses, etc.)
     *
     * @return JsonResponse
     */
    public function getMetadata(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Metadata retrieved successfully',
                'data' => [
                    'contactTypes' => TblCcPhoneCollectionDetail::getContactTypes(),
                    'callStatuses' => TblCcPhoneCollectionDetail::getCallStatuses(),
                    'rules' => [
                        'contactType' => 'Can be: rpc, tpc, rb, or null',
                        'callStatus' => 'Can be: reached, ring, busy, cancelled, power_off, wrong_number, no_contact, or null',
                        'promisedPaymentDate' => 'Must be today or future date',
                        'dtCallLater' => 'Must be today or future date',
                        'dtCallEnded' => 'Must be after dtCallStarted',
                        'uploadDocuments' => 'Must be valid JSON format',
                        'createdBy' => 'Required for audit tracking'
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get metadata', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metadata',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get recent phone collection details (for reference)
     *
     * @return JsonResponse
     */
    public function getRecent(): JsonResponse
    {
        try {
            $recentDetails = TblCcPhoneCollectionDetail::with(['standardRemark', 'creator'])
                                                     ->orderBy('createdAt', 'desc')
                                                     ->take(10)
                                                     ->get();

            return response()->json([
                'success' => true,
                'message' => 'Recent phone collection details retrieved successfully',
                'data' => $recentDetails,
                'total' => $recentDetails->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get recent phone collection details', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent records',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
