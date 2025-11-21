<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCcPhoneCollectionRequest;
use App\Http\Requests\BulkCreateCcPhoneCollectionRequest;
use App\Http\Requests\ManualAssignCallsRequest;
use App\Models\TblCcPhoneCollection;
use App\Models\User;
use App\Services\BulkPhoneCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\DB;

class TblCcPhoneCollectionController extends Controller
{
    protected $bulkService;

    public function __construct(BulkPhoneCollectionService $bulkService)
    {
        $this->bulkService = $bulkService;
    }

    /**
     * Bulk create phone collection records
     *
     * @param BulkCreateCcPhoneCollectionRequest $request
     * @return JsonResponse
     */
    public function bulkStore(BulkCreateCcPhoneCollectionRequest $request): JsonResponse
    {
        try {
            $phoneCollections = $request->validated()['phone_collections'];

            Log::info('Starting bulk create phone collections', [
                'record_count' => count($phoneCollections),
                'request_size' => strlen(json_encode($phoneCollections)) / 1024 . ' KB'
            ]);

            // Validate data integrity (check duplicates, etc.)
            // $integrityErrors = $this->bulkService->validateDataIntegrity($phoneCollections);
            // if (!empty($integrityErrors)) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Data integrity validation failed',
            //         'errors' => $integrityErrors
            //     ], 422);
            // }

            // Decide between chunked or single bulk insert based on size
            if (count($phoneCollections) > 500) {
                // Use chunked approach for large datasets
                $result = $this->bulkService->bulkInsertInChunks($phoneCollections, 500);
            } else {
                // Use single bulk insert for smaller datasets
                $result = $this->bulkService->bulkInsert($phoneCollections);
            }

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Phone collection records created successfully',
            //     'data' => [
            //         'total_records' => $result['total_records'] ?? $result['records_inserted'],
            //         'records_inserted' => $result['total_inserted'] ?? $result['records_inserted'],
            //         'execution_time' => $result['execution_time'] ?? 'N/A',
            //         'first_id' => $result['first_id'] ?? null,
            //         'last_id' => $result['last_id'] ?? null,
            //         'performance_stats' => [
            //             'records_per_second' => isset($result['execution_time']) && $result['execution_time'] > 0
            //                 ? round(($result['total_inserted'] ?? $result['records_inserted']) / $result['execution_time'], 2)
            //                 : 'N/A',
            //             'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
            //             'chunks_processed' => $result['chunks_processed'] ?? 1
            //         ]
            //     ]
            // ], 201);
            return response()->json([
                'success' => true,
                'message' => 'Phone collection records created successfully',
                'data' => [
                    'total_records' => count($phoneCollections),
                    'records_inserted' => $result['records_inserted']
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Bulk create phone collections failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_size' => strlen(json_encode($request->validated())) / 1024 . ' KB'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create phone collection records',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    /**
     * Create a new phone collection record
     *
     * @param CreateCcPhoneCollectionRequest $request
     * @return JsonResponse
     */
    public function store(CreateCcPhoneCollectionRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            Log::info('Creating phone collection record', [
                'customer_id' => $validatedData['customerId'],
                'contract_id' => $validatedData['contractId'],
                'total_amount' => $validatedData['totalAmount']
            ]);

            // Set default values for auto fields
            $validatedData['status'] = $validatedData['status'] ?? 'pending';
            $validatedData['totalAttempts'] = 0;
            $validatedData['createdBy'] = $validatedData['createdBy'] ?? 1; // TODO: Get from auth

            // Create the record
            $phoneCollection = TblCcPhoneCollection::create($validatedData);

            Log::info('Phone collection record created successfully', [
                'phoneCollectionId' => $phoneCollection->phoneCollectionId,
                'customer_name' => $phoneCollection->customerFullName,
                'total_amount' => $phoneCollection->totalAmount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phone collection record created successfully',
                'data' => [
                    'phoneCollectionId' => $phoneCollection->phoneCollectionId,
                    'status' => $phoneCollection->status,
                    'segmentType' => $phoneCollection->segmentType,
                    'contractId' => $phoneCollection->contractId,
                    'customerId' => $phoneCollection->customerId,
                    'assetId' => $phoneCollection->assetId,
                    'paymentId' => $phoneCollection->paymentId,
                    'paymentNo' => $phoneCollection->paymentNo,
                    'dueDate' => $phoneCollection->dueDate?->format('Y-m-d'),
                    'daysOverdueGross' => $phoneCollection->daysOverdueGross,
                    'daysOverdueNet' => $phoneCollection->daysOverdueNet,
                    'daysSinceLastPayment' => $phoneCollection->daysSinceLastPayment,
                    'lastPaymentDate' => $phoneCollection->lastPaymentDate?->format('Y-m-d'),
                    'paymentAmount' => $phoneCollection->paymentAmount,
                    'penaltyAmount' => $phoneCollection->penaltyAmount,
                    'totalAmount' => $phoneCollection->totalAmount,
                    'amountPaid' => $phoneCollection->amountPaid,
                    'amountUnpaid' => $phoneCollection->amountUnpaid,
                    'contractNo' => $phoneCollection->contractNo,
                    'contractDate' => $phoneCollection->contractDate?->format('Y-m-d'),
                    'contractType' => $phoneCollection->contractType,
                    'contractingProductType' => $phoneCollection->contractingProductType,
                    'customerFullName' => $phoneCollection->customerFullName,
                    'gender' => $phoneCollection->gender,
                    'birthDate' => $phoneCollection->birthDate?->format('Y-m-d'),
                    'totalAttempts' => $phoneCollection->totalAttempts,
                    'createdAt' => $phoneCollection->createdAt?->format('Y-m-d H:i:s'),
                    'createdBy' => $phoneCollection->createdBy,
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to create phone collection record', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create phone collection record',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    /**
     * Get phone collection records with filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Add with('batch') to eager load relationship
            // Also prepare to load assignedBy user info
            $query = TblCcPhoneCollection::with(['batch', 'subBatch']);

            // Filter by status if provided
            if ($request->has('status') && !empty($request->status)) {
                $query->byStatus($request->status);
            }

            // Filter by assignedTo if provided
            if ($request->has('assignedTo') && !empty($request->assignedTo)) {
                $query->byAssignedTo($request->assignedTo);
            }

            // Filter by assignedAt date range if provided
            if ($request->has('assignedAtFrom') || $request->has('assignedAtTo')) {
                $assignedAtFrom = $request->assignedAtFrom;
                $assignedAtTo = $request->assignedAtTo;

                // Validate date format for assignedAtFrom
                if ($assignedAtFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $assignedAtFrom)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format for assignedAtFrom',
                        'error' => 'Date must be in Y-m-d format (e.g., 2025-01-15)'
                    ], 400);
                }

                // Validate date format for assignedAtTo
                if ($assignedAtTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $assignedAtTo)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format for assignedAtTo',
                        'error' => 'Date must be in Y-m-d format (e.g., 2025-01-15)'
                    ], 400);
                }

                // Validate that assignedAtFrom is not after assignedAtTo
                if ($assignedAtFrom && $assignedAtTo && $assignedAtFrom > $assignedAtTo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date range',
                        'error' => 'assignedAtFrom must be before or equal to assignedAtTo'
                    ], 400);
                }

                // Apply date range filter with timezone consideration (Myanmar timezone)
                if ($assignedAtFrom && $assignedAtTo) {
                    // Both dates provided - filter by range
                    $query->whereRaw(
                        'DATE("assignedAt" AT TIME ZONE \'Asia/Yangon\') BETWEEN ? AND ?',
                        [$assignedAtFrom, $assignedAtTo]
                    );
                } elseif ($assignedAtFrom) {
                    // Only from date provided
                    $query->whereRaw(
                        'DATE("assignedAt" AT TIME ZONE \'Asia/Yangon\') >= ?',
                        [$assignedAtFrom]
                    );
                } elseif ($assignedAtTo) {
                    // Only to date provided
                    $query->whereRaw(
                        'DATE("assignedAt" AT TIME ZONE \'Asia/Yangon\') <= ?',
                        [$assignedAtTo]
                    );
                }
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'createdAt');
            $sortOrder = $request->get('sort_order', 'desc');

            // Validate sort parameters
            $allowedSortFields = [
                'phoneCollectionId', 'status', 'assignedTo', 'assignedAt',
                'totalAttempts', 'lastAttemptAt', 'createdAt', 'updatedAt',
                'dueDate', 'totalAmount', 'amountUnpaid'
            ];

            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'createdAt';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortBy, $sortOrder);

            // Get all results without pagination
            $phoneCollections = $query->get();

            // Get unique assignedBy values (excluding 1 and null) to batch query users
            $assignedByIds = $phoneCollections
                ->pluck('assignedBy')
                ->filter(function ($assignedBy) {
                    return $assignedBy !== null && $assignedBy !== 1;
                })
                ->unique()
                ->values()
                ->toArray();

            // Get unique assignedTo values (excluding null) to batch query users
            $assignedToIds = $phoneCollections
                ->pluck('assignedTo')
                ->filter(function ($assignedTo) {
                    return $assignedTo !== null;
                })
                ->unique()
                ->values()
                ->toArray();

            // Batch load users for assignedBy and assignedTo
            $assignedByUsers = [];
            $assignedToUsers = [];

            // Merge both arrays and query once for efficiency
            $allUserIds = array_unique(array_merge($assignedByIds, $assignedToIds));

            if (!empty($allUserIds)) {
                $users = User::whereIn('authUserId', $allUserIds)
                    ->get()
                    ->keyBy('authUserId');

                $assignedByUsers = $users;
                $assignedToUsers = $users;
            }

            // Transform data to include batchName, subBatchId, subBatchName, assignedByName, assignedToName
            $transformedData = $phoneCollections->map(function ($pc) use ($assignedByUsers, $assignedToUsers) {
                // Determine assignedByName
                $assignedByName = null;
                if ($pc->assignedBy !== null) {
                    if ($pc->assignedBy === 1) {
                        $assignedByName = 'System';
                    } elseif (isset($assignedByUsers[$pc->assignedBy])) {
                        $assignedByName = $assignedByUsers[$pc->assignedBy]->userFullName;
                    }
                }

                // Determine assignedToName
                $assignedToName = null;
                if ($pc->assignedTo !== null && isset($assignedToUsers[$pc->assignedTo])) {
                    $assignedToName = $assignedToUsers[$pc->assignedTo]->userFullName;
                }

                return [
                    'phoneCollectionId' => $pc->phoneCollectionId,
                    'status' => $pc->status,
                    'assignedTo' => $pc->assignedTo,
                    'assignedToName' => $assignedToName,      // ← NEW FIELD
                    'assignedBy' => $pc->assignedBy,
                    'assignedByName' => $assignedByName,
                    'assignedAt' => $pc->assignedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'totalAttempts' => $pc->totalAttempts,
                    'lastAttemptAt' => $pc->lastAttemptAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'lastAttemptBy' => $pc->lastAttemptBy,
                    'segmentType' => $pc->segmentType,
                    'contractId' => $pc->contractId,
                    'contractNo' => $pc->contractNo,
                    'customerId' => $pc->customerId,
                    'customerFullName' => $pc->customerFullName,
                    'customerAge' => $pc->customerAge,
                    'gender' => $pc->gender,
                    'hasKYCAppAccount' => $pc->hasKYCAppAccount,
                    'contractDate' => $pc->contractDate->format('Y-m-d'),
                    'contractingProductType' => $pc->contractingProductType,
                    'contractType' => $pc->contractType,
                    'paymentId' => $pc->paymentId,
                    'paymentNo' => $pc->paymentNo,
                    'dueDate' => $pc->dueDate?->format('Y-m-d'),
                    'daysOverdueGross' => $pc->daysOverdueGross,
                    'daysOverdueNet' => $pc->daysOverdueNet,
                    'totalAmount' => $pc->totalAmount,
                    'penaltyAmount' => $pc->penaltyAmount,
                    'amountUnpaid' => $pc->amountUnpaid,
                    'batchId' => $pc->batchId,
                    'batchCode' => $pc->batch?->code,
                    'batchName' => $pc->batch?->batchName,           // ← ĐỔI: batchCode → batchName
                    'subBatchId' => $pc->subBatchId,                  // ← THÊM: subBatchId
                    'subBatchName' => $pc->subBatch?->batchName,
                    'createdAt' => $pc->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'updatedAt' => $pc->updatedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Phone collections retrieved successfully',
                'data' => $transformedData,
                'total' => $phoneCollections->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve phone collections', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve phone collections',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark phone collection as completed
     *
     * @param Request $request
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function markAsCompleted(Request $request, int $phoneCollectionId): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'completedBy' => ['required', 'integer', 'exists:users,authUserId'],
            ]);

            $completedByAuthUserId = $request->input('completedBy');

            // Validate phoneCollectionId
            if ($phoneCollectionId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone collection ID',
                    'error' => 'Phone collection ID must be a positive integer'
                ], 400);
            }

            Log::info('Marking phone collection as completed', [
                'phone_collection_id' => $phoneCollectionId,
                'completed_by_auth_user_id' => $completedByAuthUserId,
            ]);

            // Find phone collection record
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                Log::warning('Phone collection not found', [
                    'phone_collection_id' => $phoneCollectionId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Phone collection not found',
                    'error' => 'The specified phone collection does not exist'
                ], 404);
            }

            // Check if already completed
            if ($phoneCollection->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'message' => 'Phone collection is already completed',
                    'data' => [
                        'phoneCollectionId' => $phoneCollection->phoneCollectionId,
                        'status' => $phoneCollection->status,
                        'contractId' => $phoneCollection->contractId,
                        'customerFullName' => $phoneCollection->customerFullName,
                        'completedBy' => $phoneCollection->completedBy,
                        'completedAt' => $phoneCollection->completedAt?->format('Y-m-d H:i:s'),
                        'updatedAt' => $phoneCollection->updatedAt?->format('Y-m-d H:i:s'),
                    ]
                ], 200);
            }

            // Verify user exists
            $completedByUser = User::where('authUserId', $completedByAuthUserId)->first();

            if (!$completedByUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => "User with authUserId {$completedByAuthUserId} does not exist"
                ], 404);
            }

            // Store previous status for logging
            $previousStatus = $phoneCollection->status;

            // Get current time in Myanmar timezone (UTC+6:30)
            $myanmarTime = now()->timezone('Asia/Yangon');

            // Update status to completed
            $phoneCollection->update([
                'status' => 'completed',
                'completedBy' => $completedByAuthUserId, // ✅ Keep authUserId
                'completedAt' => $myanmarTime,
                'updatedBy' => $completedByAuthUserId,   // ✅ Keep authUserId
                'updatedAt' => $myanmarTime,
            ]);

            Log::info('Phone collection marked as completed successfully', [
                'phone_collection_id' => $phoneCollectionId,
                'previous_status' => $previousStatus,
                'new_status' => 'completed',
                'completed_by_auth_user_id' => $completedByAuthUserId,
                'completed_at' => $myanmarTime->format('Y-m-d H:i:s'),
                'contract_id' => $phoneCollection->contractId,
                'customer_name' => $phoneCollection->customerFullName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phone collection marked as completed successfully',
                'data' => [
                    'phoneCollectionId' => $phoneCollection->phoneCollectionId,
                    'status' => $phoneCollection->status,
                    'previousStatus' => $previousStatus,
                    'contractId' => $phoneCollection->contractId,
                    'contractNo' => $phoneCollection->contractNo,
                    'customerId' => $phoneCollection->customerId,
                    'customerFullName' => $phoneCollection->customerFullName,
                    'totalAmount' => $phoneCollection->totalAmount,
                    'amountUnpaid' => $phoneCollection->amountUnpaid,
                    'totalAttempts' => $phoneCollection->totalAttempts,
                    'lastAttemptAt' => $phoneCollection->lastAttemptAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'completedBy' => $phoneCollection->completedBy, // authUserId
                    'completedAt' => $phoneCollection->completedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'updatedAt' => $phoneCollection->updatedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'updatedBy' => $phoneCollection->updatedBy, // authUserId
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to mark phone collection as completed', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark phone collection as completed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Manually assign phone collections to a specific user
     *
     * @param ManualAssignCallsRequest $request
     * @return JsonResponse
     */
    public function manualAssign(ManualAssignCallsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $assignedBy = $validated['assignedBy']; // authUserId
            $assignTo = $validated['assignTo'];     // authUserId
            $phoneCollectionIds = $validated['phoneCollectionIds'];

            Log::info('Manual assign request received', [
                'assigned_by_auth_user_id' => $assignedBy,
                'assign_to_auth_user_id' => $assignTo,
                'phone_collection_count' => count($phoneCollectionIds),
                'phone_collection_ids' => $phoneCollectionIds
            ]);

            DB::beginTransaction();

            // Tìm users bằng authUserId để verify tồn tại
            $assignedByUser = User::where('authUserId', $assignedBy)->first();
            $assignToUser = User::where('authUserId', $assignTo)->first();

            if (!$assignedByUser || !$assignToUser) {
                throw new Exception('User not found');
            }

            // Get phone collections to be assigned
            $phoneCollections = TblCcPhoneCollection::whereIn('phoneCollectionId', $phoneCollectionIds)->get();

            if ($phoneCollections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No phone collections found',
                    'error' => 'The specified phone collections do not exist'
                ], 404);
            }

            // Check if some IDs were not found
            $foundIds = $phoneCollections->pluck('phoneCollectionId')->toArray();
            $notFoundIds = array_diff($phoneCollectionIds, $foundIds);

            if (!empty($notFoundIds)) {
                Log::warning('Some phone collection IDs not found', [
                    'not_found_ids' => $notFoundIds
                ]);
            }

            // Prepare update data - KHÔNG thay đổi status
            $updateData = [
                'assignedTo' => $assignTo,      // Lưu authUserId
                'assignedBy' => $assignedBy,    // Lưu authUserId
                'assignedAt' => now(),
                'updatedBy' => $assignedBy,     // Lưu authUserId
                'updatedAt' => now(),
            ];

            // Update all phone collections
            $updatedCount = TblCcPhoneCollection::whereIn('phoneCollectionId', $foundIds)
                ->update($updateData);

            // Get updated records for response
            $updatedPhoneCollections = TblCcPhoneCollection::whereIn('phoneCollectionId', $foundIds)->get();

            // Prepare assignments array
            $assignments = $updatedPhoneCollections->map(function ($pc, $index) use ($assignToUser, $assignedByUser) {
                return [
                    'phoneCollectionId' => $pc->phoneCollectionId,
                    'contractId' => $pc->contractId,
                    'contractNo' => $pc->contractNo,
                    'customerFullName' => $pc->customerFullName,
                    'status' => $pc->status, // Giữ nguyên status hiện tại
                    'assignedTo' => [
                        'authUserId' => $assignToUser->authUserId,
                        'username' => $assignToUser->username,
                        'userFullName' => $assignToUser->userFullName,
                        'email' => $assignToUser->email,
                    ],
                    'assignedBy' => [
                        'authUserId' => $assignedByUser->authUserId,
                        'username' => $assignedByUser->username,
                        'userFullName' => $assignedByUser->userFullName,
                    ],
                    'assignedAt' => $pc->assignedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'sequence' => $index + 1
                ];
            });

            DB::commit();

            Log::info('Manual assign completed successfully', [
                'assigned_by_auth_user_id' => $assignedBy,
                'assign_to_auth_user_id' => $assignTo,
                'total_assigned' => $updatedCount,
                'not_found_count' => count($notFoundIds)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phone collections assigned successfully',
                'data' => [
                    'assignedBy' => [
                        'authUserId' => $assignedByUser->authUserId,
                        'username' => $assignedByUser->username,
                        'userFullName' => $assignedByUser->userFullName,
                    ],
                    'assignTo' => [
                        'authUserId' => $assignToUser->authUserId,
                        'username' => $assignToUser->username,
                        'userFullName' => $assignToUser->userFullName,
                        'email' => $assignToUser->email,
                    ],
                    'assignments' => $assignments,
                    'summary' => [
                        'totalRequested' => count($phoneCollectionIds),
                        'totalAssigned' => $updatedCount,
                        'notFoundIds' => $notFoundIds,
                        'assignedAt' => now()->format('Y-m-d H:i:s')
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Manual assign failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign phone collections',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Export collection report
     *
     * GET /api/cc-phone-collections/export-report?from=2025-11-01&to=2025-11-30
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function exportReport(Request $request): JsonResponse
    {
        try {
            // Validate dates
            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $fromDate = $request->input('from');
            $toDate = $request->input('to');

            Log::info('Exporting collection report', [
                'from_date' => $fromDate,
                'to_date' => $toDate
            ]);

            // Query phone collections in date range WITH promise history
            $phoneCollections = TblCcPhoneCollection::select([
                    'tbl_CcPhoneCollection.*',
                    'latest_promise.promisedPaymentDate as latest_promisedPaymentDate',
                    'latest_promise.dtCallLater as latest_dtCallLater'
                ])
                ->leftJoin('tbl_CcPromiseHistory as latest_promise', function($join) {
                    $join->on('latest_promise.paymentId', '=', 'tbl_CcPhoneCollection.paymentId')
                        ->where('latest_promise.isActive', '=', true)
                        ->whereRaw('"latest_promise"."createdAt" = (
                            SELECT MAX("ph2"."createdAt")
                            FROM "tbl_CcPromiseHistory" "ph2"
                            WHERE "ph2"."paymentId" = "tbl_CcPhoneCollection"."paymentId"
                            AND "ph2"."isActive" = true
                        )');
                })
                ->whereRaw(
                    'DATE("tbl_CcPhoneCollection"."assignedAt" AT TIME ZONE \'Asia/Yangon\') BETWEEN ? AND ?',
                    [$fromDate, $toDate]
                )
                ->orderBy('tbl_CcPhoneCollection.assignedAt', 'asc')
                ->get();

            if ($phoneCollections->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No records found for the specified date range',
                    'data' => [],
                    'total' => 0
                ], 200);
            }

            Log::info('Phone collections loaded', [
                'total_records' => $phoneCollections->count()
            ]);

            // Collect unique user IDs for batch loading
            $userIds = collect();
            $phoneCollections->each(function($pc) use ($userIds) {
                if ($pc->assignedBy) $userIds->push($pc->assignedBy);
                if ($pc->assignedTo) $userIds->push($pc->assignedTo);
                if ($pc->lastAttemptBy) $userIds->push($pc->lastAttemptBy);
            });
            $userIds = $userIds->unique()->filter()->values()->toArray();

            // Batch load users
            $users = [];
            if (!empty($userIds)) {
                $users = User::whereIn('authUserId', $userIds)
                    ->get()
                    ->keyBy('authUserId');
            }

            // Get phone collection IDs for loading attempts
            $phoneCollectionIds = $phoneCollections->pluck('phoneCollectionId')->toArray();

            // Batch load latest attempts
            $latestAttempts = DB::table('tbl_CcPhoneCollectionDetail as pcd1')
                ->select('pcd1.*')
                ->whereIn('pcd1.phoneCollectionId', $phoneCollectionIds)
                ->whereRaw('"pcd1"."phoneCollectionDetailId" = (
                    SELECT "pcd2"."phoneCollectionDetailId"
                    FROM "tbl_CcPhoneCollectionDetail" "pcd2"
                    WHERE "pcd2"."phoneCollectionId" = "pcd1"."phoneCollectionId"
                    ORDER BY "pcd2"."dtCallStarted" DESC
                    LIMIT 1
                )')
                ->get()
                ->keyBy('phoneCollectionId');

            // Get unique case result IDs from attempts
            $caseResultIds = $latestAttempts->pluck('callResultId')->filter()->unique()->values()->toArray();

            // Batch load case results
            $caseResults = [];
            if (!empty($caseResultIds)) {
                $caseResults = DB::table('tbl_CcCaseResult')
                    ->whereIn('caseResultId', $caseResultIds)
                    ->get()
                    ->keyBy('caseResultId');
            }

            // Get unique reason IDs from attempts
            $reasonIds = $latestAttempts->pluck('reasonId')->filter()->unique()->values()->toArray();

            // Batch load reasons
            $reasons = [];
            if (!empty($reasonIds)) {
                $reasons = DB::table('tbl_CcReason')
                    ->whereIn('reasonId', $reasonIds)
                    ->get()
                    ->keyBy('reasonId');
            }

            // Get unique batch IDs from phone collections
            $batchIds = $phoneCollections->pluck('batchId')->filter()->unique()->values()->toArray();

            // Batch load batches
            $batches = [];
            if (!empty($batchIds)) {
                $batches = DB::table('tbl_CcBatch')
                    ->whereIn('batchId', $batchIds)
                    ->get()
                    ->keyBy('batchId');
            }

            // Batch load user levels (need both assignedTo and batchId)
            // First, create a map of authUserId -> userId (local ID)
            $authUserIds = $phoneCollections
                ->pluck('assignedTo')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $authToLocalUserIdMap = [];
            if (!empty($authUserIds)) {
                $localUsers = User::whereIn('authUserId', $authUserIds)
                    ->get(['id', 'authUserId']);

                foreach ($localUsers as $user) {
                    $authToLocalUserIdMap[$user->authUserId] = $user->id;
                }
            }

            // Create array of [userId (local), batchId] pairs
            $userLevelPairs = $phoneCollections
                ->filter(function($pc) use ($authToLocalUserIdMap) {
                    return $pc->assignedTo !== null
                        && $pc->batchId !== null
                        && isset($authToLocalUserIdMap[$pc->assignedTo]);
                })
                ->map(function($pc) use ($authToLocalUserIdMap) {
                    return [
                        'userId' => $authToLocalUserIdMap[$pc->assignedTo],
                        'authUserId' => $pc->assignedTo,
                        'batchId' => $pc->batchId
                    ];
                })
                ->unique(function($pair) {
                    return $pair['userId'] . '_' . $pair['batchId'];
                })
                ->values();

            // Load user levels using userId (local ID)
            $userLevels = [];
            if ($userLevelPairs->isNotEmpty()) {
                $userLevelsRaw = DB::table('tbl_CcUserLevel')
                    ->whereIn('userId', $userLevelPairs->pluck('userId')->unique()->toArray())
                    ->whereIn('batchId', $userLevelPairs->pluck('batchId')->unique()->toArray())
                    ->get();

                // Create a map: userId_batchId -> level
                $userLevelsByUserIdBatchId = [];
                foreach ($userLevelsRaw as $userLevel) {
                    $key = $userLevel->userId . '_' . $userLevel->batchId;
                    $userLevelsByUserIdBatchId[$key] = $userLevel;
                }

                // Convert to authUserId_batchId for easy lookup in map function
                foreach ($userLevelPairs as $pair) {
                    $userIdKey = $pair['userId'] . '_' . $pair['batchId'];
                    $authUserIdKey = $pair['authUserId'] . '_' . $pair['batchId'];

                    if (isset($userLevelsByUserIdBatchId[$userIdKey])) {
                        $userLevels[$authUserIdKey] = $userLevelsByUserIdBatchId[$userIdKey];
                    }
                }
            }

            // ============================================================
            // Count askingPostponePayment per contractId (FULL HISTORY)
            // ============================================================
            $contractIds = $phoneCollections->pluck('contractId')->filter()->unique()->toArray();
            $postponeCountByContract = [];

            if (!empty($contractIds)) {
                $postponeCounts = DB::table('tbl_CcPhoneCollectionDetail as pcd')
                    ->join('tbl_CcPhoneCollection as pc', 'pc.phoneCollectionId', '=', 'pcd.phoneCollectionId')
                    ->whereIn('pc.contractId', $contractIds)
                    ->where('pcd.askingPostponePayment', '=', true)
                    ->groupBy('pc.contractId')
                    ->select([
                        'pc.contractId',
                        DB::raw('COUNT(*) as postpone_count')
                    ])
                    ->get();

                foreach ($postponeCounts as $count) {
                    $postponeCountByContract[$count->contractId] = (int)$count->postpone_count;
                }

                Log::info('Counted askingPostponePayment per contract', [
                    'total_contracts_with_postpone' => count($postponeCountByContract)
                ]);
            }
            // ============================================================

            // Transform data
            $reportData = $phoneCollections->map(function($pc) use ($users, $latestAttempts, $caseResults, $reasons, $postponeCountByContract, $batches, $userLevels) {
                // Get user names
                $assignedByName = null;
                if ($pc->assignedBy) {
                    if ($pc->assignedBy === 1) {
                        $assignedByName = 'System';
                    } elseif (isset($users[$pc->assignedBy])) {
                        $assignedByName = $users[$pc->assignedBy]->userFullName;
                    }
                }

                $assignedToName = isset($users[$pc->assignedTo])
                    ? $users[$pc->assignedTo]->userFullName
                    : null;

                $lastAttemptByName = isset($users[$pc->lastAttemptBy])
                    ? $users[$pc->lastAttemptBy]->userFullName
                    : null;

                // Get latest attempt details
                $latestAttempt = $latestAttempts->get($pc->phoneCollectionId);

                $attemptData = [
                    'dtCallStarted' => null,
                    'dtCallEnded' => null,
                    'duration' => null,
                    'callStatus' => null,
                    'callResultName' => null,
                    'standardRemarkContent' => null,
                    'notPayingReason' => null,
                ];

                if ($latestAttempt) {
                    $attemptData['dtCallStarted'] = $latestAttempt->dtCallStarted;
                    $attemptData['dtCallEnded'] = $latestAttempt->dtCallEnded;
                    $attemptData['callStatus'] = $latestAttempt->callStatus;
                    $attemptData['standardRemarkContent'] = $latestAttempt->standardRemarkContent;

                    // Calculate duration in seconds
                    if ($latestAttempt->dtCallStarted && $latestAttempt->dtCallEnded) {
                        $start = \Carbon\Carbon::parse($latestAttempt->dtCallStarted);
                        $end = \Carbon\Carbon::parse($latestAttempt->dtCallEnded);
                        $attemptData['duration'] = $end->diffInSeconds($start);
                    }

                    // Get case result name
                    if ($latestAttempt->callResultId && isset($caseResults[$latestAttempt->callResultId])) {
                        $attemptData['callResultName'] = $caseResults[$latestAttempt->callResultId]->caseResultName;
                    }

                    // Get reason name (not paying reason)
                    if ($latestAttempt->reasonId && isset($reasons[$latestAttempt->reasonId])) {
                        $attemptData['notPayingReason'] = $reasons[$latestAttempt->reasonId]->reasonName;
                    }
                }

                // Get batch name (MOVED OUTSIDE if block)
                $batchName = null;
                if ($pc->batchId && isset($batches[$pc->batchId])) {
                    $batchName = $batches[$pc->batchId]->batchName;
                }

                // Get user level (MOVED OUTSIDE if block)
                $userLevel = null;
                if ($pc->assignedTo && $pc->batchId) {
                    $userLevelKey = $pc->assignedTo . '_' . $pc->batchId;
                    if (isset($userLevels[$userLevelKey])) {
                        $userLevel = $userLevels[$userLevelKey]->level;
                    }
                }

                return [
                    'salesAreaName' => $pc->salesAreaName,
                    'branch' => $pc->contractPlaceName,
                    'customerId' => $pc->customerId,
                    'birthDate' => $pc->birthDate?->format('Y-m-d'),
                    'gender' => $pc->gender,
                    'contractNo' => $pc->contractNo,
                    'contractDate' => $pc->contractDate?->format('Y-m-d'),
                    'contractingProductType' => $pc->contractingProductType,
                    'productName' => $pc->productName,
                    'productColor' => $pc->productColor,
                    'plateNo' => $pc->plateNo,
                    'unitPrice' => $pc->unitPrice,
                    'paymentNo' => $pc->paymentNo,
                    'paymentStatus' => $pc->paymentStatus,
                    'lastPaymentDate' => $pc->lastPaymentDate?->format('Y-m-d'),
                    'dueDate' => $pc->dueDate?->format('Y-m-d'),
                    'paymentAmount' => $pc->paymentAmount,
                    'penaltyAmount' => $pc->penaltyAmount,
                    'penaltyExempted' => $pc->penaltyExempted,
                    'totalAmount' => $pc->totalAmount,
                    'amountPaid' => $pc->amountPaid,
                    'amountUnpaid' => $pc->amountUnpaid,
                    'batchName' => $batchName,              // ← THÊM MỚI
                    'DPD' => $pc->daysOverdueGross,
                    'assignedByName' => $assignedByName,
                    'assignedToName' => $assignedToName,
                    'userLevel' => $userLevel,
                    'assignedAt' => $pc->assignedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'lastAttemptBy' => $lastAttemptByName,
                    'dtCallStarted' => $attemptData['dtCallStarted'],
                    'dtCallEnded' => $attemptData['dtCallEnded'],
                    'duration' => $attemptData['duration'],
                    'callStatus' => $attemptData['callStatus'],
                    'callResultName' => $attemptData['callResultName'],
                    'notPayingReason' => $attemptData['notPayingReason'],
                    'standardRemarkContent' => $attemptData['standardRemarkContent'],
                    'uncall' => $pc->lastAttemptAt === null,
                    'reschedule' => $pc->reschedule,
                    'phoneNo1' => $pc->phoneNo1,
                    'phoneNo2' => $pc->phoneNo2,
                    'phoneNo3' => $pc->phoneNo3,
                    'homeAddress' => $pc->homeAddress,
                    'noOfPenaltyFeesCharged' => $pc->noOfPenaltyFeesCharged,
                    'noOfPenaltyFeesExempted' => $pc->noOfPenaltyFeesExempted,
                    'noOfPenaltyFeesPaid' => $pc->noOfPenaltyFeesPaid,
                    'totalPenaltyAmountCharged' => $pc->totalPenaltyAmountCharged,

                    // ============================================================
                    // Promise and Postpone Info
                    // ============================================================
                    'promisedPaymentDate' => $pc->latest_promisedPaymentDate
                        ? \Carbon\Carbon::parse($pc->latest_promisedPaymentDate)->format('Y-m-d')
                        : null,
                    'dtCallLater' => $pc->latest_dtCallLater
                        ? \Carbon\Carbon::parse($pc->latest_dtCallLater)->utc()->format('Y-m-d\TH:i:s\Z')
                        : null,
                    'noOfAskingPostponePayment' => $postponeCountByContract[$pc->contractId] ?? 0,
                    // ============================================================

                    'source' => 'phone-collection',
                ];
            });

            Log::info('Collection report generated successfully', [
                'total_records' => $reportData->count(),
                'from_date' => $fromDate,
                'to_date' => $toDate
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Collection report generated successfully',
                'data' => $reportData,
                'total' => $reportData->count(),
                'date_range' => [
                    'from' => $fromDate,
                    'to' => $toDate
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to generate collection report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate collection report',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
