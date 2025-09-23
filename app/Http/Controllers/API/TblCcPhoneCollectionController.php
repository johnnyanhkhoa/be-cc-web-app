<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCcPhoneCollectionRequest;
use App\Http\Requests\BulkCreateCcPhoneCollectionRequest;
use App\Models\TblCcPhoneCollection;
use App\Services\BulkPhoneCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

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
}
