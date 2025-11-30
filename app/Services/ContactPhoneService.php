<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ContactPhoneService
{
    /**
     * Get all phones for a customer
     *
     * @param int $customerId
     * @return array
     * @throws Exception
     */
    public function getPhones(int $customerId): array
    {
        try {
            // $url = "https://maximus-staging.vnapp.xyz/api/v1/cc/customers/{$customerId}/phones";
            $url = "https://maximus.vnapp.xyz/api/v1/cc/customers/{$customerId}/phones";

            Log::info('Getting customer phones', [
                'url' => $url,
                'customer_id' => $customerId,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => 't03JN3y8L12gzVbuLuorjwBAHgVAkkY6QOvJkP6m',
                ])
                ->get($url);

            $data = $response->json();

            Log::info('Customer phones retrieved', [
                'customer_id' => $customerId,
                'status' => $response->status(),
                'success' => $data['status'] ?? null,
            ]);

            // Return raw response exactly as received
            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get customer phones', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create new phone for a customer
     *
     * @param int $customerId
     * @param array $phoneData
     * @return array
     * @throws Exception
     */
    public function createPhone(int $customerId, array $phoneData): array
    {
        try {
            // $url = "https://maximus-staging.vnapp.xyz/api/v1/cc/customers/{$customerId}/phones";
            $url = "https://maximus.vnapp.xyz/api/v1/cc/customers/{$customerId}/phones";

            Log::info('Creating customer phone', [
                'url' => $url,
                'customer_id' => $customerId,
                'phone_data' => $phoneData,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => 't03JN3y8L12gzVbuLuorjwBAHgVAkkY6QOvJkP6m',
                ])
                ->post($url, $phoneData);

            $data = $response->json();

            Log::info('Customer phone created', [
                'customer_id' => $customerId,
                'status' => $response->status(),
                'phone_id' => $data['data']['phoneId'] ?? null,
            ]);

            // Return raw response exactly as received
            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to create customer phone', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update existing phone for a customer
     *
     * @param int $customerId
     * @param int $phoneId
     * @param array $phoneData
     * @return array
     * @throws Exception
     */
    public function updatePhone(int $customerId, int $phoneId, array $phoneData): array
    {
        try {
            // $url = "https://maximus-staging.vnapp.xyz/api/v1/cc/customers/{$customerId}/phones/{$phoneId}";
            $url = "https://maximus.vnapp.xyz/api/v1/cc/customers/{$customerId}/phones/{$phoneId}";

            Log::info('Updating customer phone', [
                'url' => $url,
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'phone_data' => $phoneData,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => 't03JN3y8L12gzVbuLuorjwBAHgVAkkY6QOvJkP6m',
                ])
                ->post($url, $phoneData);

            $data = $response->json();

            Log::info('Customer phone updated', [
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'status' => $response->status(),
            ]);

            // Return raw response exactly as received
            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to update customer phone', [
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete phone for a customer
     *
     * @param int $customerId
     * @param int $phoneId
     * @param array $deleteData  // ← THÊM DÒNG NÀY
     * @return array
     * @throws Exception
     */
    public function deletePhone(int $customerId, int $phoneId, array $deleteData): array
    {
        try {
            // $url = "https://maximus-staging.vnapp.xyz/api/v1/cc/customers/{$customerId}/phones/{$phoneId}";
            $url = "https://maximus.vnapp.xyz/api/v1/cc/customers/{$customerId}/phones/{$phoneId}";

            Log::info('Deleting customer phone', [
                'url' => $url,
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'delete_data' => $deleteData,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',  // ← THÊM DÒNG NÀY
                    'x-api-key' => 't03JN3y8L12gzVbuLuorjwBAHgVAkkY6QOvJkP6m',
                ])
                ->delete($url, $deleteData);  // ← THÊM $deleteData VÀO ĐÂY

            $data = $response->json();

            Log::info('Customer phone deleted', [
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'status' => $response->status(),
            ]);

            // Return raw response exactly as received
            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to delete customer phone', [
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
