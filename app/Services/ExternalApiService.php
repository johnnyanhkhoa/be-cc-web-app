<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ExternalApiService
{
    private const BASE_URL = 'https://maximus.vnapp.xyz/api/v1/cc/phone-collection';
    private const API_KEY = 't03JN3y8L12gzVbuLuorjwBAHgVAkkY6QOvJkP6m';

    /**
     * Fetch contract details by contractId
     */
    public function fetchContractDetails(int $contractId): array
    {
        try {
            $url = self::BASE_URL . '/contracts/' . $contractId;

            Log::info('Fetching contract details from external API', [
                'url' => $url,
                'contract_id' => $contractId
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->get($url);

            // Always return the JSON response, regardless of status
            $data = $response->json();

            if ($response->successful()) {
                Log::info('Contract details fetched successfully from external API', [
                    'contract_id' => $contractId,
                    'status' => $data['status'] ?? 'unknown'
                ]);
            } else {
                Log::warning('External API returned error response', [
                    'contract_id' => $contractId,
                    'status_code' => $response->status(),
                    'response' => $data
                ]);
            }

            // If response is not successful, throw exception with the exact response body
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body(), $response->status());
            }

            return $data;

        } catch (Exception $e) {
            Log::error('Failed to fetch contract details from external API', [
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Fetch past-due contracts
     */
    public function fetchPastDueContracts(array $batchData): array
    {
        // Existing method - giữ nguyên
        try {
            $url = self::BASE_URL . '/past-due/contracts';

            Log::info('Fetching past-due contracts', [
                'url' => $url,
                'batch_data' => $batchData
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->post($url, [
                    'batchType' => $batchData['type'],
                    'batchCode' => $batchData['code'],
                    'batchIntensity' => $batchData['intensity']
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Past-due contracts fetched successfully', [
                    'total_contracts' => $data['data']['total'] ?? 0
                ]);

                return $data;
            }

            throw new Exception('API request failed: ' . $response->body(), $response->status());

        } catch (Exception $e) {
            Log::error('Failed to fetch past-due contracts', [
                'error' => $e->getMessage(),
                'batch_data' => $batchData
            ]);
            throw $e;
        }
    }

    /**
     * Fetch pre-due contracts
     */
    public function fetchPreDueContracts(array $batchData): array
    {
        // Existing method - giữ nguyên
        try {
            $url = self::BASE_URL . '/pre-due/contracts';

            Log::info('Fetching pre-due contracts', [
                'url' => $url,
                'batch_data' => $batchData
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->post($url, [
                    'batchType' => $batchData['type'],
                    'batchCode' => $batchData['code'],
                    'batchIntensity' => $batchData['intensity']
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Pre-due contracts fetched successfully', [
                    'total_contracts' => $data['data']['total'] ?? 0
                ]);

                return $data;
            }

            throw new Exception('API request failed: ' . $response->body(), $response->status());

        } catch (Exception $e) {
            Log::error('Failed to fetch pre-due contracts', [
                'error' => $e->getMessage(),
                'batch_data' => $batchData
            ]);
            throw $e;
        }
    }
}
