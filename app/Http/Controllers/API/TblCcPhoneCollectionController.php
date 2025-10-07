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
            $query = TblCcPhoneCollection::query();

            // Filter by status if provided
            if ($request->has('status') && !empty($request->status)) {
                $query->byStatus($request->status);
            }

            // Filter by assignedTo if provided
            if ($request->has('assignedTo') && !empty($request->assignedTo)) {
                $query->byAssignedTo($request->assignedTo);
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

            return response()->json([
                'success' => true,
                'message' => 'Phone collections retrieved successfully',
                'data' => $phoneCollections,
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
            // Validate phoneCollectionId
            if ($phoneCollectionId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone collection ID',
                    'error' => 'Phone collection ID must be a positive integer'
                ], 400);
            }

            Log::info('Marking phone collection as completed', [
                'phone_collection_id' => $phoneCollectionId
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
                        'updatedAt' => $phoneCollection->updatedAt?->format('Y-m-d H:i:s'),
                    ]
                ], 200);
            }

            // Get current user ID (TODO: replace with actual authenticated user)
            $updatedBy = $request->input('updatedBy', 1);

            // Store previous status for logging
            $previousStatus = $phoneCollection->status;

            // Update status to completed
            $phoneCollection->update([
                'status' => 'completed',
                'updatedBy' => $updatedBy,
                'updatedAt' => now(),
            ]);

            Log::info('Phone collection marked as completed successfully', [
                'phone_collection_id' => $phoneCollectionId,
                'previous_status' => $previousStatus,
                'new_status' => 'completed',
                'updated_by' => $updatedBy,
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
                    'lastAttemptAt' => $phoneCollection->lastAttemptAt?->format('Y-m-d H:i:s'),
                    'updatedAt' => $phoneCollection->updatedAt?->format('Y-m-d H:i:s'),
                    'updatedBy' => $phoneCollection->updatedBy,
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

            // Prepare update data - Lưu authUserId thay vì id
            $updateData = [
                'assignedTo' => $assignTo,      // Lưu authUserId trực tiếp
                'assignedBy' => $assignedBy,    // Lưu authUserId trực tiếp
                'assignedAt' => now(),
                'status' => 'assigned',
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
                    'assignedAt' => $pc->assignedAt?->format('Y-m-d H:i:s'),
                    'status' => $pc->status,
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
}
