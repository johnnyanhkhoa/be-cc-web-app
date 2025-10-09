<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCcPhoneCollectionDetailRequest;
use App\Models\TblCcPhoneCollectionDetail;
use App\Models\TblCcPhoneCollection;
use App\Models\TblCcRemark;
use App\Models\TblCcCaseResult;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class TblCcPhoneCollectionDetailController extends Controller
{
    protected $imageUploadService;

    public function __construct(ImageUploadService $imageUploadService)
    {
        $this->imageUploadService = $imageUploadService;
    }

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

            Log::info('Creating phone collection detail with JSON', [
                'phone_collection_id' => $validatedData['phoneCollectionId'],
                'contact_type' => $validatedData['contactType'] ?? null,
                'call_status' => $validatedData['callStatus'] ?? null,
                'createdBy' => $validatedData['createdBy'],
                'has_upload_image_ids' => isset($validatedData['uploadImageIds']) && !empty($validatedData['uploadImageIds'])
            ]);

            DB::beginTransaction();

            // Handle uploadDocuments JSON
            $uploadDocumentsJson = null;
            if (isset($validatedData['uploadImageIds']) && !empty($validatedData['uploadImageIds'])) {
                $uploadDocumentsJson = ['uploadImageId' => $validatedData['uploadImageIds']];

                Log::info('Using uploaded image IDs', [
                    'upload_image_ids' => $validatedData['uploadImageIds']
                ]);
            } elseif (isset($validatedData['uploadDocuments'])) {
                // Fallback: if uploadDocuments JSON is provided directly
                $uploadDocumentsJson = is_string($validatedData['uploadDocuments'])
                    ? json_decode($validatedData['uploadDocuments'], true)
                    : $validatedData['uploadDocuments'];
            }

            // Remove uploadImageIds from data and set uploadDocuments
            unset($validatedData['uploadImageIds']);
            $validatedData['uploadDocuments'] = $uploadDocumentsJson;

            // Create the record
            $phoneCollectionDetail = TblCcPhoneCollectionDetail::create($validatedData);

            // Update phone collection attempts count
            $phoneCollection = TblCcPhoneCollection::find($validatedData['phoneCollectionId']);
            if ($phoneCollection) {
                $phoneCollection->increment('totalAttempts');
                $phoneCollection->update([
                    'lastAttemptAt' => now(),
                    'lastAttemptBy' => $validatedData['createdBy'],
                    'updatedBy' => $validatedData['createdBy'],
                ]);
            }

            DB::commit();

            // Load relationships for response
            $phoneCollectionDetail->load(['standardRemark', 'creator', 'phoneCollection']);

            // Get uploaded images for response
            $uploadedImagesData = [];
            if ($uploadDocumentsJson && isset($uploadDocumentsJson['uploadImageId'])) {
                $uploadedImagesData = $this->imageUploadService->getImagesByIds($uploadDocumentsJson['uploadImageId']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Phone collection detail created successfully',
                'data' => [
                    'phoneCollectionDetailId' => $phoneCollectionDetail->phoneCollectionDetailId,
                    'phoneCollectionId' => $phoneCollectionDetail->phoneCollectionId,
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
                    'dtCallLater' => $phoneCollectionDetail->dtCallLater?->format('Y-m-d H:i:s'),
                    'dtCallStarted' => $phoneCollectionDetail->dtCallStarted?->format('Y-m-d H:i:s'),
                    'dtCallEnded' => $phoneCollectionDetail->dtCallEnded?->format('Y-m-d H:i:s'),
                    'updatePhoneRequest' => $phoneCollectionDetail->updatePhoneRequest,
                    'updatePhoneRemark' => $phoneCollectionDetail->updatePhoneRemark,
                    'standardRemarkId' => $phoneCollectionDetail->standardRemarkId,
                    'standardRemarkContent' => $phoneCollectionDetail->standardRemarkContent,
                    'reschedulingEvidence' => $phoneCollectionDetail->reschedulingEvidence,
                    'uploadDocuments' => $phoneCollectionDetail->uploadDocuments,
                    'uploadedImages' => $uploadedImagesData,
                    'createdAt' => $phoneCollectionDetail->createdAt?->format('Y-m-d H:i:s'),
                    'createdBy' => $phoneCollectionDetail->createdBy,
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create phone collection detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create phone collection detail',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get call attempts for a specific phone collection
     *
     * @param Request $request
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function getCallAttempts(Request $request, int $phoneCollectionId): JsonResponse
    {
        try {
            // Validate phoneCollectionId
            if ($phoneCollectionId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone collection ID',
                    'error' => 'Phone collection ID must be a positive integer'
                ], 400);
            }

            Log::info('Fetching call attempts for phone collection', [
                'phone_collection_id' => $phoneCollectionId
            ]);

            // Check if phone collection exists
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);
            if (!$phoneCollection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone collection not found',
                    'error' => 'The specified phone collection does not exist'
                ], 404);
            }

            // Get call attempts with relationships
            $attempts = TblCcPhoneCollectionDetail::with(['standardRemark', 'callResult', 'creator'])
                ->byPhoneCollectionId($phoneCollectionId)
                ->orderBy('createdAt', 'desc')
                ->get();

            // Transform data for response
            $transformedAttempts = $attempts->map(function ($attempt) {
                // Get uploaded images if exists
                $uploadedImages = [];
                if ($attempt->uploadDocuments && isset($attempt->uploadDocuments['uploadImageId'])) {
                    $uploadedImages = $this->imageUploadService->getImagesByIds($attempt->uploadDocuments['uploadImageId']);
                }

                return [
                    'phoneCollectionDetailId' => $attempt->phoneCollectionDetailId,
                    'phoneCollectionId' => $attempt->phoneCollectionId,
                    'contactType' => $attempt->contactType,
                    'phoneId' => $attempt->phoneId,
                    'contactDetailId' => $attempt->contactDetailId,
                    'contactPhoneNumer' => $attempt->contactPhoneNumer,
                    'contactName' => $attempt->contactName,
                    'contactRelation' => $attempt->contactRelation,
                    'callStatus' => $attempt->callStatus,
                    'callResultId' => $attempt->callResultId,
                    'leaveMessage' => $attempt->leaveMessage,
                    'remark' => $attempt->remark,
                    'promisedPaymentDate' => $attempt->promisedPaymentDate?->format('Y-m-d'),
                    'askingPostponePayment' => $attempt->askingPostponePayment,
                    'dtCallLater' => $attempt->dtCallLater?->format('Y-m-d H:i:s'),
                    'dtCallStarted' => $attempt->dtCallStarted?->format('Y-m-d H:i:s'),
                    'dtCallEnded' => $attempt->dtCallEnded?->format('Y-m-d H:i:s'),
                    'updatePhoneRequest' => $attempt->updatePhoneRequest,
                    'updatePhoneRemark' => $attempt->updatePhoneRemark,
                    'standardRemarkId' => $attempt->standardRemarkId,
                    'standardRemarkContent' => $attempt->standardRemarkContent,
                    'reschedulingEvidence' => $attempt->reschedulingEvidence,
                    'uploadDocuments' => $attempt->uploadDocuments, // Original JSON
                    'uploadedImages' => $uploadedImages, // Expanded image data with fileName, localUrl, googleUrl
                    'createdAt' => $attempt->createdAt?->format('Y-m-d H:i:s'),
                    'createdBy' => $attempt->createdBy,
                    // Related data
                    'standardRemark' => $attempt->standardRemark,
                    'callResult' => $attempt->callResult,
                    'creator' => $attempt->creator ? [
                        'id' => $attempt->creator->id,
                        'username' => $attempt->creator->username,
                        'userFullName' => $attempt->creator->userFullName,
                    ] : null,
                ];
            });

            Log::info('Call attempts fetched successfully', [
                'phone_collection_id' => $phoneCollectionId,
                'total_attempts' => $attempts->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call attempts retrieved successfully',
                'data' => [
                    'phoneCollectionId' => $phoneCollectionId,
                    'phoneCollection' => [
                        'phoneCollectionId' => $phoneCollection->phoneCollectionId,
                        'contractId' => $phoneCollection->contractId,
                        'customerFullName' => $phoneCollection->customerFullName,
                        'status' => $phoneCollection->status,
                        'totalAttempts' => $phoneCollection->totalAttempts,
                        'lastAttemptAt' => $phoneCollection->lastAttemptAt?->format('Y-m-d H:i:s'),
                        'segmentType' => $phoneCollection->segmentType,
                        'dueDate' => $phoneCollection->dueDate?->format('Y-m-d'),
                        'totalAmount' => $phoneCollection->totalAmount,
                        'amountUnpaid' => $phoneCollection->amountUnpaid,
                    ],
                    'attempts' => $transformedAttempts,
                    'totalAttempts' => $attempts->count(),
                    'summary' => [
                        'byContactType' => $attempts->groupBy('contactType')->map->count(),
                        'byCallStatus' => $attempts->groupBy('callStatus')->map->count(),
                        'latestAttempt' => $attempts->first()?->createdAt?->format('Y-m-d H:i:s'),
                        'oldestAttempt' => $attempts->last()?->createdAt?->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch call attempts', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve call attempts',
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
                        'phoneCollectionId' => 'Required - Must be a valid phone collection ID',
                        'contactType' => 'Can be: rpc, tpc, rb, or null',
                        'callStatus' => 'Can be: reached, ring, busy, cancelled, power_off, wrong_number, no_contact, or null',
                        'promisedPaymentDate' => 'Must be today or future date',
                        'dtCallLater' => 'Must be today or future date',
                        'dtCallEnded' => 'Must be after dtCallStarted',
                        'uploadDocuments' => 'Array of image files (JPEG, PNG, GIF, WEBP, max 5MB each)',
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
            $recentDetails = TblCcPhoneCollectionDetail::with(['standardRemark', 'creator', 'phoneCollection'])
                                                     ->orderBy('createdAt', 'desc')
                                                     ->take(10)
                                                     ->get();

            // Transform data with uploaded images
            $transformedDetails = $recentDetails->map(function ($detail) {
                // Get uploaded images if exists
                $uploadedImages = [];
                if ($detail->uploadDocuments && isset($detail->uploadDocuments['uploadImageId'])) {
                    $uploadedImages = $this->imageUploadService->getImagesByIds($detail->uploadDocuments['uploadImageId']);
                }

                return [
                    'phoneCollectionDetailId' => $detail->phoneCollectionDetailId,
                    'phoneCollectionId' => $detail->phoneCollectionId,
                    'contactType' => $detail->contactType,
                    'callStatus' => $detail->callStatus,
                    'remark' => $detail->remark,
                    'uploadDocuments' => $detail->uploadDocuments,
                    'uploadedImages' => $uploadedImages,
                    'createdAt' => $detail->createdAt?->format('Y-m-d H:i:s'),
                    'phoneCollection' => $detail->phoneCollection ? [
                        'contractId' => $detail->phoneCollection->contractId,
                        'customerFullName' => $detail->phoneCollection->customerFullName,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Recent phone collection details retrieved successfully',
                'data' => $transformedDetails,
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

    /**
     * Get all remarks for a specific contract
     *
     * @param Request $request
     * @param int $contractId
     * @return JsonResponse
     */
    public function getRemarksByContract(Request $request, int $contractId): JsonResponse
    {
        try {
            // Validate contractId
            if ($contractId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid contract ID',
                    'error' => 'Contract ID must be a positive integer'
                ], 400);
            }

            Log::info('Fetching remarks for contract', [
                'contract_id' => $contractId
            ]);

            // Get all phone collections for this contract
            $phoneCollections = TblCcPhoneCollection::where('contractId', $contractId)
                ->select('phoneCollectionId', 'contractId', 'customerFullName', 'status', 'totalAttempts')
                ->get();

            if ($phoneCollections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No phone collections found for this contract',
                    'error' => 'The specified contract has no phone collection records'
                ], 404);
            }

            $phoneCollectionIds = $phoneCollections->pluck('phoneCollectionId')->toArray();

            // Get all call details with remarks for these phone collections
            $callDetails = TblCcPhoneCollectionDetail::with(['standardRemark', 'creator', 'phoneCollection'])
                ->whereIn('phoneCollectionId', $phoneCollectionIds)
                ->where(function($query) {
                    // Only get records that have remarks
                    $query->whereNotNull('remark')
                        ->orWhereNotNull('standardRemarkContent')
                        ->orWhereNotNull('standardRemarkId');
                })
                ->orderBy('createdAt', 'desc')
                ->get();

            // Transform data for response
            $transformedRemarks = $callDetails->map(function ($detail) {
                return [
                    'phoneCollectionDetailId' => $detail->phoneCollectionDetailId,
                    'phoneCollectionId' => $detail->phoneCollectionId,
                    'contractId' => $detail->phoneCollection->contractId ?? null,
                    'customerFullName' => $detail->phoneCollection->customerFullName ?? null,
                    'remark' => $detail->remark,
                    'standardRemarkContent' => $detail->standardRemarkContent,
                    'standardRemarkId' => $detail->standardRemarkId,
                    'standardRemark' => $detail->standardRemark ? [
                        'remarkId' => $detail->standardRemark->remarkId,
                        'remarkContent' => $detail->standardRemark->remarkContent,
                        'contactType' => $detail->standardRemark->contactType,
                    ] : null,
                    'contactType' => $detail->contactType,
                    'callStatus' => $detail->callStatus,
                    'contactPhoneNumer' => $detail->contactPhoneNumer,
                    'createdAt' => $detail->createdAt?->format('Y-m-d H:i:s'),
                    'createdBy' => $detail->createdBy,
                    'creator' => $detail->creator ? [
                        'id' => $detail->creator->id,
                        'username' => $detail->creator->username,
                        'userFullName' => $detail->creator->userFullName,
                    ] : null,
                ];
            });

            // Group remarks by phoneCollectionId
            $remarksByPhoneCollection = $transformedRemarks->groupBy('phoneCollectionId');

            Log::info('Contract remarks fetched successfully', [
                'contract_id' => $contractId,
                'phone_collections_count' => $phoneCollections->count(),
                'total_remarks' => $callDetails->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract remarks retrieved successfully',
                'data' => [
                    'contractId' => $contractId,
                    'phoneCollections' => $phoneCollections->map(function($pc) use ($remarksByPhoneCollection) {
                        return [
                            'phoneCollectionId' => $pc->phoneCollectionId,
                            'customerFullName' => $pc->customerFullName,
                            'status' => $pc->status,
                            'totalAttempts' => $pc->totalAttempts,
                            'remarks' => $remarksByPhoneCollection->get($pc->phoneCollectionId, collect())->values(),
                            'remarkCount' => $remarksByPhoneCollection->get($pc->phoneCollectionId, collect())->count(),
                        ];
                    }),
                    'allRemarks' => $transformedRemarks->values(), // Flat list of all remarks
                    'summary' => [
                        'totalPhoneCollections' => $phoneCollections->count(),
                        'totalRemarks' => $callDetails->count(),
                        'remarksWithStandardRemark' => $callDetails->whereNotNull('standardRemarkId')->count(),
                        'remarksWithCustomRemark' => $callDetails->whereNotNull('remark')->count(),
                        'byContactType' => $callDetails->groupBy('contactType')->map->count(),
                        'byCallStatus' => $callDetails->groupBy('callStatus')->map->count(),
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch contract remarks', [
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contract remarks',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
