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
use App\Models\VwCcPhoneCollectionBasic;
use App\Models\VwCcPhoneCollectionDetailRemarks;

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

            Log::info('Creating phone collection detail', [
                'phone_collection_id' => $validatedData['phoneCollectionId'],
                'contact_type' => $validatedData['contactType'] ?? null,
                'call_status' => $validatedData['callStatus'] ?? null,
                'createdBy' => $validatedData['createdBy']
            ]);

            DB::beginTransaction();

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

            if (isset($validatedData['promisedPaymentDate']) || isset($validatedData['dtCallLater'])) {
                \App\Models\TblCcPromiseHistory::create([
                    'contractId' => $phoneCollection->contractId,
                    'phoneCollectionId' => $phoneCollection->phoneCollectionId,
                    'phoneCollectionDetailId' => $phoneCollectionDetail->phoneCollectionDetailId,
                    'paymentId' => $phoneCollection->paymentId,
                    'promiseType' => (isset($validatedData['promisedPaymentDate']) && isset($validatedData['dtCallLater']))
                        ? 'both'
                        : (isset($validatedData['promisedPaymentDate']) ? 'promised_payment' : 'call_later'),
                    'promisedPaymentDate' => $validatedData['promisedPaymentDate'] ?? null,
                    'dtCallLater' => $validatedData['dtCallLater'] ?? null,
                    'isActive' => true,
                    'createdAt' => now(),
                    'createdBy' => $validatedData['createdBy'] ?? null,
                ]);

                Log::info('Promise history saved', [
                    'phoneCollectionDetailId' => $phoneCollectionDetail->phoneCollectionDetailId,
                    'promisedPaymentDate' => $validatedData['promisedPaymentDate'] ?? null,
                    'dtCallLater' => $validatedData['dtCallLater'] ?? null,
                ]);
            }

            DB::commit();

            // Load relationships for response
            $phoneCollectionDetail->load(['standardRemark', 'creator', 'phoneCollection', 'uploadImages']);

            // Get uploaded images for response
            $uploadedImagesData = $phoneCollectionDetail->uploadImages->map(function ($image) {
                return [
                    'uploadImageId' => $image->uploadImageId,
                    'fileName' => $image->fileName,
                    'fileType' => $image->fileType,
                    'localUrl' => $image->localUrl,
                    'googleUrl' => $image->googleUrl,
                    'createdAt' => $image->createdAt?->format('Y-m-d H:i:s'),
                ];
            })->toArray();

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
                    'reasonId' => $phoneCollectionDetail->reasonId,
                    'leaveMessage' => $phoneCollectionDetail->leaveMessage,
                    'remark' => $phoneCollectionDetail->remark,
                    'promisedPaymentDate' => $phoneCollectionDetail->promisedPaymentDate?->format('Y-m-d'),
                    'askingPostponePayment' => $phoneCollectionDetail->askingPostponePayment,
                    'dtCallLater' => $phoneCollectionDetail->dtCallLater?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'dtCallStarted' => $phoneCollectionDetail->dtCallStarted?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'dtCallEnded' => $phoneCollectionDetail->dtCallEnded?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'updatePhoneRequest' => $phoneCollectionDetail->updatePhoneRequest,
                    'updatePhoneRemark' => $phoneCollectionDetail->updatePhoneRemark,
                    'standardRemarkId' => $phoneCollectionDetail->standardRemarkId,
                    'standardRemarkContent' => $phoneCollectionDetail->standardRemarkContent,
                    'reschedulingEvidence' => $phoneCollectionDetail->reschedulingEvidence,
                    'uploadedImages' => $uploadedImagesData,
                    'createdAt' => $phoneCollectionDetail->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
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
            $attempts = TblCcPhoneCollectionDetail::with(['standardRemark', 'callResult', 'reason', 'uploadImages'])
                ->byPhoneCollectionId($phoneCollectionId)
                ->orderBy('createdAt', 'desc')
                ->get();

            // Transform data for response
            $transformedAttempts = $attempts->map(function ($attempt) {
                // Get uploaded images from relationship
                $uploadedImages = $attempt->uploadImages->map(function ($image) {
                    return [
                        'uploadImageId' => $image->uploadImageId,
                        'fileName' => $image->fileName,
                        'fileType' => $image->fileType,
                        'localUrl' => $image->localUrl,
                        'googleUrl' => $image->googleUrl,
                        'createdAt' => $image->createdAt?->format('Y-m-d H:i:s'),
                    ];
                })->toArray();

                // Get creator by authUserId
                $createdByUser = null;
                if ($attempt->createdBy) {
                    $user = \App\Models\User::where('authUserId', $attempt->createdBy)->first();
                    $createdByUser = $user ? $user->userFullName : null;
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
                    'reasonId' => $attempt->reasonId,
                    'reasonName' => $attempt->reason?->reasonName,           // ← THÊM DÒNG NÀY
                    'reasonRemark' => $attempt->reason?->reasonRemark,
                    'leaveMessage' => $attempt->leaveMessage,
                    'remark' => $attempt->remark,
                    'promisedPaymentDate' => $attempt->promisedPaymentDate?->format('Y-m-d'),
                    'askingPostponePayment' => $attempt->askingPostponePayment,
                    'dtCallLater' => $attempt->dtCallLater?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'dtCallStarted' => $attempt->dtCallStarted?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'dtCallEnded' => $attempt->dtCallEnded?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'updatePhoneRequest' => $attempt->updatePhoneRequest,
                    'updatePhoneRemark' => $attempt->updatePhoneRemark,
                    'standardRemarkId' => $attempt->standardRemarkId,
                    'standardRemarkContent' => $attempt->standardRemarkContent,
                    'reschedulingEvidence' => $attempt->reschedulingEvidence,
                    'uploadedImages' => $uploadedImages,
                    'createdAt' => $attempt->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'createdBy' => $createdByUser, // Changed: Now returns userFullName
                    // Related data
                    'standardRemark' => $attempt->standardRemark,
                    'callResult' => $attempt->callResult,
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
                        'lastAttemptAt' => $phoneCollection->lastAttemptAt?->utc()->format('Y-m-d\TH:i:s\Z'),
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
                        'latestAttempt' => $attempts->first()?->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                        'oldestAttempt' => $attempts->last()?->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
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
                                        ->get([
                                            'caseResultId',
                                            'caseResultName',
                                            'caseResultRemark',
                                            'contactType',
                                            'batchId',
                                            'requiredField'
                                        ]);

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
     * Get case results by phone collection ID
     * Query phone collection → get batchId → get case results for that batch
     *
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function getCaseResultsByPhoneCollection(int $phoneCollectionId): JsonResponse
    {
        try {
            Log::info('Getting case results by phone collection', [
                'phone_collection_id' => $phoneCollectionId
            ]);

            // Step 1: Get phone collection to find batchId
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone collection not found',
                    'error' => 'The specified phone collection does not exist'
                ], 404);
            }

            if (!$phoneCollection->subBatchId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No sub-batch assigned to this phone collection',
                    'error' => 'subBatchId is null for this phone collection'
                ], 400);
            }

            $subBatchId = $phoneCollection->subBatchId;

            Log::info('Found subBatchId for phone collection', [
                'phone_collection_id' => $phoneCollectionId,
                'sub_batch_id' => $subBatchId
            ]);

            // Step 2: Get case results for this sub-batch
            $caseResults = TblCcCaseResult::active()
                                        ->byBatch($subBatchId)
                                        ->orderBy('caseResultName')
                                        ->get([
                                            'caseResultId',
                                            'caseResultName',
                                            'caseResultRemark',
                                            'contactType',
                                            'batchId',
                                            'requiredField'
                                        ]);

            Log::info('Case results retrieved', [
                'sub_batch_id' => $subBatchId,
                'results_count' => $caseResults->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case results retrieved successfully',
                'data' => [
                    'phone_collection' => [
                        'id' => $phoneCollection->phoneCollectionId,
                        'batch_id' => $phoneCollection->batchId,      // Parent batch
                        'sub_batch_id' => $subBatchId,                 // Sub-batch (used for query)
                        'contract_no' => $phoneCollection->contractNo,
                        'customer_name' => $phoneCollection->customerFullName,
                    ],
                    'case_results' => $caseResults,
                    'total_results' => $caseResults->count()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get case results by phone collection', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            $recentDetails = TblCcPhoneCollectionDetail::with(['standardRemark', 'creator', 'phoneCollection', 'uploadImages'])
                ->orderBy('createdAt', 'desc')
                ->take(10)
                ->get();

            // Transform data with uploaded images
            $transformedDetails = $recentDetails->map(function ($detail) {
                // Get uploaded images from relationship
                $uploadedImages = $detail->uploadImages->map(function ($image) {
                    return [
                        'uploadImageId' => $image->uploadImageId,
                        'fileName' => $image->fileName,
                        'fileType' => $image->fileType,
                        'localUrl' => $image->localUrl,
                        'googleUrl' => $image->googleUrl,
                        'createdAt' => $image->createdAt?->format('Y-m-d H:i:s'),
                    ];
                })->toArray();

                return [
                    'phoneCollectionDetailId' => $detail->phoneCollectionDetailId,
                    'phoneCollectionId' => $detail->phoneCollectionId,
                    'contactType' => $detail->contactType,
                    'callStatus' => $detail->callStatus,
                    'remark' => $detail->remark,
                    'uploadedImages' => $uploadedImages,
                    'createdAt' => $detail->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
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
            if ($contractId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid contract ID',
                    'error' => 'Contract ID must be a positive integer'
                ], 400);
            }

            Log::info('Fetching remarks for contract (all years)', [
                'contract_id' => $contractId
            ]);

            // Get phone collections from view
            $phoneCollections = VwCcPhoneCollectionBasic::where('contractId', $contractId)->get();

            if ($phoneCollections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No phone collections found for this contract',
                ], 404);
            }

            $phoneCollectionIds = $phoneCollections->pluck('phoneCollectionId')->toArray();

            // Get remarks from view (already filtered by WHERE in view)
            $callDetails = VwCcPhoneCollectionDetailRemarks::with([
                'standardRemark',
                'creator',
                'phoneCollection',
                'reason',
                'uploadImages'
            ])
            ->whereIn('phoneCollectionId', $phoneCollectionIds)
            ->orderBy('createdAt', 'desc')
            ->get();

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
                    'reasonId' => $detail->reasonId,
                    'reasonName' => $detail->reason?->reasonName,
                    'reasonRemark' => $detail->reason?->reasonRemark,
                    'contactType' => $detail->contactType,
                    'callStatus' => $detail->callStatus,
                    'contactPhoneNumer' => $detail->contactPhoneNumer,
                    'createdAt' => $detail->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'createdBy' => $detail->createdBy,
                    'creator' => $detail->creator ? [
                        'id' => $detail->creator->id,
                        'username' => $detail->creator->username,
                        'userFullName' => $detail->creator->userFullName,
                    ] : null,
                    'uploadedImages' => $detail->uploadImages->map(function ($image) {
                        return [
                            'uploadImageId' => $image->uploadImageId,
                            'fileName' => $image->fileName,
                            'fileType' => $image->fileType,
                            'localUrl' => $image->localUrl,
                            'googleUrl' => $image->googleUrl,
                            'createdAt' => $image->createdAt?->format('Y-m-d H:i:s'),
                        ];
                    })->toArray(),
                ];
            });

            $remarksByPhoneCollection = $transformedRemarks->groupBy('phoneCollectionId');

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
                    'allRemarks' => $transformedRemarks->values(),
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
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contract remarks',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
