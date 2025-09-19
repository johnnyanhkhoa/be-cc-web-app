<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCcPhoneCollectionRequest;
use App\Models\TblCcPhoneCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class TblCcPhoneCollectionController extends Controller
{
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
            $validatedData['personCreated'] = $validatedData['personCreated'] ?? 1; // TODO: Get from auth

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
                    'dtCreated' => $phoneCollection->dtCreated?->format('Y-m-d H:i:s'),
                    'personCreated' => $phoneCollection->personCreated,
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
            $sortBy = $request->get('sort_by', 'dtCreated');
            $sortOrder = $request->get('sort_order', 'desc');

            // Validate sort parameters
            $allowedSortFields = [
                'phoneCollectionId', 'status', 'assignedTo', 'assignedAt',
                'totalAttempts', 'lastAttemptAt', 'dtCreated', 'dtUpdated',
                'dueDate', 'totalAmount', 'amountUnpaid'
            ];

            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'dtCreated';
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
