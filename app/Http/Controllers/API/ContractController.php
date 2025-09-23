<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ExternalApiService;
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
     * Get contract details by contractId
     * Returns exact same response as external API
     *
     * @param Request $request
     * @param int $contractId
     * @return JsonResponse
     */
    public function getContractDetails(Request $request, int $contractId): JsonResponse
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

            Log::info('Fetching contract details via proxy API', [
                'contract_id' => $contractId,
                'requested_by' => 'system' // TODO: Get from auth when available
            ]);

            // Fetch contract details from external API
            $contractData = $this->externalApiService->fetchContractDetails($contractId);

            Log::info('Contract details fetched successfully via proxy', [
                'contract_id' => $contractId,
                'status' => $contractData['status'] ?? 'unknown'
            ]);

            // Return exact same response as external API
            return response()->json($contractData, 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch contract details via proxy', [
                'contract_id' => $contractId,
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
     * Get contract summary (if you still want this endpoint)
     * This method can be removed if you only want exact proxy behavior
     */
    public function getContractSummary(Request $request, int $contractId): JsonResponse
    {
        try {
            // Get full contract details
            $response = $this->getContractDetails($request, $contractId);
            $contractData = $response->getData(true);

            // If external API returned error, pass it through
            if (!isset($contractData['status']) || $contractData['status'] !== 1) {
                return $response;
            }

            $data = $contractData['data'];

            // Return summary in same format as external API
            $summary = [
                'contractId' => $data['contractId'],
                'contractNo' => $data['contractNo'],
                'contractType' => $data['contractType'],
                'contractDate' => $data['contractDate'],
                'customer' => [
                    'customerId' => $data['customer']['customerId'],
                    'customerFullName' => $data['customer']['customerFullName'],
                    'gender' => $data['customer']['gender'],
                    'natRegCardNo' => $data['customer']['natRegCardNo'],
                ],
                'con_asset' => [
                    'assetId' => $data['con_asset']['assetId'] ?? null,
                    'brandName' => $data['con_asset']['brandName'] ?? null,
                    'modelName' => $data['con_asset']['modelName'] ?? null,
                    'productName' => $data['con_asset']['productName'] ?? null,
                    'unitPrice' => $data['con_asset']['unitPrice'] ?? null,
                ],
                'salesAreaName' => $data['salesAreaName'],
            ];

            return response()->json([
                'status' => 1,
                'data' => $summary,
                'message' => 'Contract summary retrieved successfully.'
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to fetch contract summary', [
                'contract_id' => $contractId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve contract summary.',
                'data' => null
            ], 500);
        }
    }
}
