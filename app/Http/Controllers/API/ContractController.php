<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ExternalApiService;
use App\Models\TblCcPhoneCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ContractController extends Controller
{
    protected $externalApiService;

    public function __construct(ExternalApiService $externalApiService)
    {
        $this->externalApiService = $externalApiService;
    }

    /**
     * Get contract details by phoneCollectionId
     * Find contractId from tbl_CcPhoneCollection and fetch from external API
     *
     * @param Request $request
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function getContractDetails(Request $request, int $phoneCollectionId): JsonResponse
    {
        try {
            // Validate phoneCollectionId
            if ($phoneCollectionId <= 0) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid phone collection ID. ID must be a positive integer.',
                    'data' => null
                ], 400);
            }

            Log::info('Looking up contract by phoneCollectionId', [
                'phone_collection_id' => $phoneCollectionId
            ]);

            // Find the phone collection record
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                Log::warning('Phone collection record not found', [
                    'phone_collection_id' => $phoneCollectionId
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => 'Phone collection record not found.',
                    'data' => null
                ], 404);
            }

            $contractId = $phoneCollection->contractId;

            Log::info('Found phone collection record', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'customer_name' => $phoneCollection->customerFullName
            ]);

            // Fetch contract details from external API using contractId
            $contractData = $this->externalApiService->fetchContractDetails($contractId);

            Log::info('Contract details fetched successfully via phone collection lookup', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'status' => $contractData['status'] ?? 'unknown'
            ]);

            // Return exact same response as external API
            return response()->json($contractData, 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch contract details via phone collection lookup', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Handle different HTTP status codes from external API
            $statusCode = $e->getCode();

            // If it's a valid HTTP status code, use it
            if ($statusCode >= 400 && $statusCode < 600) {
                // Try to parse error response if it's JSON
                $errorMessage = $e->getMessage();
                if (str_contains($errorMessage, 'API request failed:')) {
                    $responseBody = str_replace('API request failed: ', '', $errorMessage);
                    $decodedResponse = json_decode($responseBody, true);

                    if ($decodedResponse && is_array($decodedResponse)) {
                        return response()->json($decodedResponse, $statusCode);
                    }
                }

                // Fallback error response in same format as external API
                return response()->json([
                    'status' => 0,
                    'message' => 'Contract not found or external API error.',
                    'data' => null
                ], $statusCode);
            }

            // For non-HTTP errors (network, timeout, etc.)
            return response()->json([
                'status' => 0,
                'message' => 'Unable to fetch contract details. Please try again later.',
                'data' => null
            ], 500);
        }
    }

    /**
     * Get contract details directly by contractId (optional endpoint)
     * This is a direct proxy without phone collection lookup
     *
     * @param Request $request
     * @param int $contractId
     * @return JsonResponse
     */
    public function getContractDetailsByContractId(Request $request, int $contractId): JsonResponse
    {
        try {
            // Validate contractId
            if ($contractId <= 0) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid contract ID. Contract ID must be a positive integer.',
                    'data' => null
                ], 400);
            }

            Log::info('Fetching contract details directly by contractId', [
                'contract_id' => $contractId
            ]);

            // Fetch contract details from external API
            $contractData = $this->externalApiService->fetchContractDetails($contractId);

            Log::info('Contract details fetched successfully (direct)', [
                'contract_id' => $contractId,
                'status' => $contractData['status'] ?? 'unknown'
            ]);

            // Return exact same response as external API
            return response()->json($contractData, 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch contract details (direct)', [
                'contract_id' => $contractId,
                'error' => $e->getMessage()
            ]);

            $statusCode = $e->getCode();

            if ($statusCode >= 400 && $statusCode < 600) {
                $errorMessage = $e->getMessage();
                if (str_contains($errorMessage, 'API request failed:')) {
                    $responseBody = str_replace('API request failed: ', '', $errorMessage);
                    $decodedResponse = json_decode($responseBody, true);

                    if ($decodedResponse && is_array($decodedResponse)) {
                        return response()->json($decodedResponse, $statusCode);
                    }
                }

                return response()->json([
                    'status' => 0,
                    'message' => 'Contract not found or external API error.',
                    'data' => null
                ], $statusCode);
            }

            return response()->json([
                'status' => 0,
                'message' => 'Unable to fetch contract details. Please try again later.',
                'data' => null
            ], 500);
        }
    }

    /**
     * Get phone collection info along with contract details
     *
     * @param Request $request
     * @param int $phoneCollectionId
     * @return JsonResponse
     */
    public function getPhoneCollectionWithContract(Request $request, int $phoneCollectionId): JsonResponse
    {
        try {
            // Find the phone collection record
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Phone collection record not found.',
                    'data' => null
                ], 404);
            }

            // Get contract details
            $contractData = $this->externalApiService->fetchContractDetails($phoneCollection->contractId);

            // If external API failed
            if (!isset($contractData['status']) || $contractData['status'] !== 1) {
                return response()->json($contractData, 200);
            }

            // Combine phone collection data with contract data
            $combinedData = [
                'phone_collection' => [
                    'phoneCollectionId' => $phoneCollection->phoneCollectionId,
                    'status' => $phoneCollection->status,
                    'assignedTo' => $phoneCollection->assignedTo,
                    'assignedAt' => $phoneCollection->assignedAt,
                    'totalAttempts' => $phoneCollection->totalAttempts,
                    'lastAttemptAt' => $phoneCollection->lastAttemptAt,
                    'segmentType' => $phoneCollection->segmentType,
                    'dueDate' => $phoneCollection->dueDate,
                    'daysOverdueGross' => $phoneCollection->daysOverdueGross,
                    'daysOverdueNet' => $phoneCollection->daysOverdueNet,
                    'totalAmount' => $phoneCollection->totalAmount,
                    'amountUnpaid' => $phoneCollection->amountUnpaid,
                ],
                'contract' => $contractData['data']
            ];

            return response()->json([
                'status' => 1,
                'data' => $combinedData,
                'message' => 'Phone collection with contract details retrieved successfully.'
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch phone collection with contract details', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Unable to fetch phone collection with contract details.',
                'data' => null
            ], 500);
        }
    }
}
