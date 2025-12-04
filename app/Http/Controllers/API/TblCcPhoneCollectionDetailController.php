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

            // Get contractId to query across all years
            $contractId = $phoneCollection->contractId;

            // Get all phoneCollectionIds for this contract from all years (2020-2025)
            $allPhoneCollectionIds = VwCcPhoneCollectionBasic::where('contractId', $contractId)
                ->pluck('phoneCollectionId')
                ->toArray();

            // Get ALL call attempts from all years (including those without remarks)
            $attempts = collect();

            // 1. Get from current table (tbl_CcPhoneCollectionDetail)
            $currentAttempts = TblCcPhoneCollectionDetail::with(['standardRemark', 'callResult', 'reason', 'uploadImages'])
                ->whereIn('phoneCollectionId', $allPhoneCollectionIds)
                ->get();
            $attempts = $attempts->merge($currentAttempts);

            // 2. Get from old partitioned tables (2020-2025)
            $oldYears = [2020, 2021, 2022, 2023, 2024, 2025];
            foreach ($oldYears as $year) {
                $tableName = "tbl_CcPhoneCollectionDetail_{$year}";

                // Check if table exists before querying
                $tableExists = DB::select("SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = ?
                )", [$tableName]);

                if ($tableExists[0]->exists) {
                    $oldAttempts = DB::table($tableName)
                        ->whereIn('phoneCollectionId', $allPhoneCollectionIds)
                        ->get();

                    // Map to add relationships manually for old records
                    $oldAttempts->each(function($attempt) {
                        // Load standardRemark if exists
                        if (isset($attempt->standardRemarkId) && $attempt->standardRemarkId) {
                            $attempt->standardRemark = \App\Models\TblCcRemark::find($attempt->standardRemarkId);
                        } else {
                            $attempt->standardRemark = null;
                        }

                        // Load callResult if exists
                        if (isset($attempt->callResultId) && $attempt->callResultId) {
                            $attempt->callResult = \App\Models\TblCcCaseResult::find($attempt->callResultId);
                        } else {
                            $attempt->callResult = null;
                        }

                        // Load reason if exists
                        if (isset($attempt->reasonId) && $attempt->reasonId) {
                            $attempt->reason = \App\Models\TblCcReason::find($attempt->reasonId);
                        } else {
                            $attempt->reason = null;
                        }

                        // Load uploadImages from old table
                        $attempt->uploadImagesOld = \App\Models\TblCcUploadImageOld::where('phoneCollectionDetailId', $attempt->phoneCollectionDetailId)
                            ->whereNull('deletedAt')
                            ->get();
                        $attempt->uploadImages = collect(); // Empty for old records
                    });

                    $attempts = $attempts->merge($oldAttempts);
                }
            }

            // Sort by createdAt descending
            $attempts = $attempts->sortByDesc(function($attempt) {
                return isset($attempt->createdAt) ? $attempt->createdAt : null;
            })->values();

            // Transform data for response
            $transformedAttempts = $attempts->map(function ($attempt) {
                // Merge uploaded images from both new and old tables
                $allImages = collect();
                if (isset($attempt->uploadImages)) {
                    $allImages = $allImages->merge($attempt->uploadImages);
                }
                if (isset($attempt->uploadImagesOld)) {
                    $allImages = $allImages->merge($attempt->uploadImagesOld);
                }

                $uploadedImages = $allImages->map(function ($image) {
                    return [
                        'uploadImageId' => $image->uploadImageId ?? null,
                        'fileName' => $image->fileName ?? null,
                        'fileType' => $image->fileType ?? null,
                        'localUrl' => $image->localUrl ?? null,
                        'googleUrl' => $image->googleUrl ?? null,
                        'createdAt' => isset($image->createdAt) ?
                            (is_string($image->createdAt) ? $image->createdAt : $image->createdAt->format('Y-m-d H:i:s')) : null,
                    ];
                })->toArray();

                // Handle creator - use userCreatedBy for old records
                $createdByUser = null;
                if (isset($attempt->createdBy) && $attempt->createdBy) {
                    // New table: authUserId
                    $user = \App\Models\User::where('authUserId', $attempt->createdBy)->first();
                    $createdByUser = $user ? $user->userFullName : null;
                } elseif (isset($attempt->userCreatedBy) && $attempt->userCreatedBy) {
                    // Old table: username string
                    $createdByUser = $attempt->userCreatedBy;
                }

                // Handle dates for both Eloquent models and stdClass
                $dtCallStarted = null;
                if (isset($attempt->dtCallStarted) && $attempt->dtCallStarted) {
                    $dtCallStarted = is_object($attempt->dtCallStarted) && method_exists($attempt->dtCallStarted, 'utc')
                        ? $attempt->dtCallStarted->utc()->format('Y-m-d\TH:i:s\Z')
                        : (is_string($attempt->dtCallStarted) ? \Carbon\Carbon::parse($attempt->dtCallStarted)->utc()->format('Y-m-d\TH:i:s\Z') : null);
                }

                $dtCallEnded = null;
                if (isset($attempt->dtCallEnded) && $attempt->dtCallEnded) {
                    $dtCallEnded = is_object($attempt->dtCallEnded) && method_exists($attempt->dtCallEnded, 'utc')
                        ? $attempt->dtCallEnded->utc()->format('Y-m-d\TH:i:s\Z')
                        : (is_string($attempt->dtCallEnded) ? \Carbon\Carbon::parse($attempt->dtCallEnded)->utc()->format('Y-m-d\TH:i:s\Z') : null);
                }

                $dtCallLater = null;
                if (isset($attempt->dtCallLater) && $attempt->dtCallLater) {
                    $dtCallLater = is_object($attempt->dtCallLater) && method_exists($attempt->dtCallLater, 'utc')
                        ? $attempt->dtCallLater->utc()->format('Y-m-d\TH:i:s\Z')
                        : (is_string($attempt->dtCallLater) ? \Carbon\Carbon::parse($attempt->dtCallLater)->utc()->format('Y-m-d\TH:i:s\Z') : null);
                }

                $promisedPaymentDate = null;
                if (isset($attempt->promisedPaymentDate) && $attempt->promisedPaymentDate) {
                    $promisedPaymentDate = is_object($attempt->promisedPaymentDate) && method_exists($attempt->promisedPaymentDate, 'format')
                        ? $attempt->promisedPaymentDate->format('Y-m-d')
                        : (is_string($attempt->promisedPaymentDate) ? \Carbon\Carbon::parse($attempt->promisedPaymentDate)->format('Y-m-d') : null);
                }

                $createdAt = null;
                if (isset($attempt->createdAt) && $attempt->createdAt) {
                    $createdAt = is_object($attempt->createdAt) && method_exists($attempt->createdAt, 'utc')
                        ? $attempt->createdAt->utc()->format('Y-m-d\TH:i:s\Z')
                        : (is_string($attempt->createdAt) ? \Carbon\Carbon::parse($attempt->createdAt)->utc()->format('Y-m-d\TH:i:s\Z') : null);
                }

                return [
                    'phoneCollectionDetailId' => $attempt->phoneCollectionDetailId ?? null,
                    'phoneCollectionId' => $attempt->phoneCollectionId ?? null,
                    'contactType' => $attempt->contactType ?? null,
                    'phoneId' => $attempt->phoneId ?? null,
                    'contactDetailId' => $attempt->contactDetailId ?? null,
                    'contactPhoneNumer' => $attempt->contactPhoneNumer ?? null,
                    'contactName' => $attempt->contactName ?? null,
                    'contactRelation' => $attempt->contactRelation ?? null,
                    'callStatus' => $attempt->callStatus ?? null,
                    'callResultId' => $attempt->callResultId ?? null,
                    'reasonId' => $attempt->reasonId ?? null,
                    'reasonName' => isset($attempt->reason) && $attempt->reason ? $attempt->reason->reasonName : null,
                    'reasonRemark' => isset($attempt->reason) && $attempt->reason ? $attempt->reason->reasonRemark : null,
                    'leaveMessage' => $attempt->leaveMessage ?? null,
                    'remark' => $attempt->remark ?? null,
                    'promisedPaymentDate' => $promisedPaymentDate,
                    'askingPostponePayment' => $attempt->askingPostponePayment ?? null,
                    'dtCallLater' => $dtCallLater,
                    'dtCallStarted' => $dtCallStarted,
                    'dtCallEnded' => $dtCallEnded,
                    'updatePhoneRequest' => $attempt->updatePhoneRequest ?? null,
                    'updatePhoneRemark' => $attempt->updatePhoneRemark ?? null,
                    'standardRemarkId' => $attempt->standardRemarkId ?? null,
                    'standardRemarkContent' => $attempt->standardRemarkContent ?? null,
                    'reschedulingEvidence' => $attempt->reschedulingEvidence ?? null,
                    'uploadedImages' => $uploadedImages,
                    'createdAt' => $createdAt,
                    'createdBy' => $createdByUser,
                    // Related data
                    'standardRemark' => isset($attempt->standardRemark) ? $attempt->standardRemark : null,
                    'callResult' => isset($attempt->callResult) ? $attempt->callResult : null,
                ];
            });

            // Calculate summary safely
            $summary = [
                'byContactType' => [],
                'byCallStatus' => [],
                'latestAttempt' => null,
                'oldestAttempt' => null,
            ];

            if ($attempts->count() > 0) {
                $summary['byContactType'] = $attempts->groupBy('contactType')->map->count()->toArray();
                $summary['byCallStatus'] = $attempts->groupBy('callStatus')->map->count()->toArray();

                $firstAttempt = $attempts->first();
                if ($firstAttempt && isset($firstAttempt->createdAt)) {
                    $summary['latestAttempt'] = is_object($firstAttempt->createdAt) && method_exists($firstAttempt->createdAt, 'utc')
                        ? $firstAttempt->createdAt->utc()->format('Y-m-d\TH:i:s\Z')
                        : (is_string($firstAttempt->createdAt) ? \Carbon\Carbon::parse($firstAttempt->createdAt)->utc()->format('Y-m-d\TH:i:s\Z') : null);
                }

                $lastAttempt = $attempts->last();
                if ($lastAttempt && isset($lastAttempt->createdAt)) {
                    $summary['oldestAttempt'] = is_object($lastAttempt->createdAt) && method_exists($lastAttempt->createdAt, 'utc')
                        ? $lastAttempt->createdAt->utc()->format('Y-m-d\TH:i:s\Z')
                        : (is_string($lastAttempt->createdAt) ? \Carbon\Carbon::parse($lastAttempt->createdAt)->utc()->format('Y-m-d\TH:i:s\Z') : null);
                }
            }

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
                    'summary' => $summary
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

            if (!$phoneCollection->batchId) {  // ← SỬA: subBatchId → batchId
                return response()->json([
                    'success' => false,
                    'message' => 'No batch assigned to this phone collection',
                    'error' => 'batchId is null for this phone collection'
                ], 400);
            }

            $batchId = $phoneCollection->batchId;  // ← SỬA: dùng batchId

            Log::info('Found batchId for phone collection', [  // ← SỬA log message
                'phone_collection_id' => $phoneCollectionId,
                'batch_id' => $batchId  // ← SỬA log key
            ]);

            // Step 2: Get case results for this batch
            $caseResults = TblCcCaseResult::active()
                                        ->byBatch($batchId)  // ← SỬA: dùng batchId
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
                'batch_id' => $batchId,  // ← SỬA log key
                'results_count' => $caseResults->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case results retrieved successfully',
                'data' => [
                    'phone_collection' => [
                        'id' => $phoneCollection->phoneCollectionId,
                        'batch_id' => $phoneCollection->batchId,
                        'sub_batch_id' => $phoneCollection->subBatchId,  // ← VẪN GIỮ ĐỂ SHOW
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
            // ✅ Handle creator - use userCreatedBy from old tables if createdBy is null
            $creatorInfo = null;
            if ($detail->creator) {
                // New table: has user relationship
                $creatorInfo = [
                    'id' => $detail->creator->id,
                    'username' => $detail->creator->username,
                    'userFullName' => $detail->creator->userFullName,
                ];
            } elseif ($detail->userCreatedBy) {
                // Old table: use userCreatedBy as username
                $creatorInfo = [
                    'id' => null,
                    'username' => $detail->userCreatedBy,
                    'userFullName' => $detail->userCreatedBy,
                ];
            }

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
                'createdBy' => $detail->createdBy ?? $detail->userCreatedBy,  // ✅ Fallback to userCreatedBy
                'creator' => $creatorInfo,  // ✅ Use processed creator info
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
