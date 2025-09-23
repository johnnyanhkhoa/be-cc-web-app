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
     * Fetch past-due contracts
     */
    public function fetchPastDueContracts(array $batchData): array
    {
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
