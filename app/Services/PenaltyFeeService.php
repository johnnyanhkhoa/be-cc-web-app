<?php

namespace App\Services;

use App\Models\TblCcPhoneCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PenaltyFeeService
{
    // private const BASE_URL = 'https://maximus-staging.vnapp.xyz/api/v1/cc';
    private const BASE_URL = 'https://maximus.vnapp.xyz/api/v1/cc';
    private const API_KEY = 't03JN3y8L12gzVbuLuorjwBAHgVAkkY6QOvJkP6m';

    /**
     * Get penalty fee info by phoneCollectionId
     *
     * @param int $phoneCollectionId
     * @return array
     * @throws Exception
     */
    public function getPenaltyFeeInfo(int $phoneCollectionId): array
    {
        try {
            // Step 1: Get paymentId from tbl_CcPhoneCollection
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                throw new Exception("Phone collection not found with ID: {$phoneCollectionId}", 404);
            }

            if (!$phoneCollection->paymentId) {
                throw new Exception("Payment ID not found for phone collection ID: {$phoneCollectionId}", 404);
            }

            $paymentId = $phoneCollection->paymentId;

            Log::info('Getting penalty fee info', [
                'phone_collection_id' => $phoneCollectionId,
                'payment_id' => $paymentId,
            ]);

            // Step 2: Call external API
            $url = self::BASE_URL . "/payments/{$paymentId}/penalty-fee";

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->get($url);

            $data = $response->json();

            Log::info('Penalty fee info retrieved', [
                'phone_collection_id' => $phoneCollectionId,
                'payment_id' => $paymentId,
                'status' => $response->status(),
            ]);

            // Return exact response from external API
            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get penalty fee info', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Exempt penalty fee by phoneCollectionId
     *
     * @param int $phoneCollectionId
     * @param string $reasonExempted
     * @return array
     * @throws Exception
     */
    public function exemptPenaltyFee(int $phoneCollectionId, string $reasonExempted, string $userExempted): array
    {
        try {
            // Step 1: Get paymentId from tbl_CcPhoneCollection
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                throw new Exception("Phone collection not found with ID: {$phoneCollectionId}", 404);
            }

            if (!$phoneCollection->paymentId) {
                throw new Exception("Payment ID not found for phone collection ID: {$phoneCollectionId}", 404);
            }

            $paymentId = $phoneCollection->paymentId;

            Log::info('Exempting penalty fee', [
                'phone_collection_id' => $phoneCollectionId,
                'payment_id' => $paymentId,
                'reason' => $reasonExempted,
                'user' => $userExempted,
            ]);

            // Step 2: Call external API
            $url = self::BASE_URL . "/payments/{$paymentId}/penalty-fee/exempt";

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->post($url, [
                    'reasonExempted' => $reasonExempted,
                    'userExempted' => $userExempted,
                ]);

            $data = $response->json();

            Log::info('Penalty fee exempted', [
                'phone_collection_id' => $phoneCollectionId,
                'payment_id' => $paymentId,
                'status' => $response->status(),
            ]);

            // Return exact response from external API
            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to exempt penalty fee', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Transfer contract to litigation by phoneCollectionId
     *
     * @param int $phoneCollectionId
     * @return array
     * @throws Exception
     */
    public function transferToLitigation(int $phoneCollectionId): array
    {
        try {
            // Step 1: Get contractId from tbl_CcPhoneCollection
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                throw new Exception("Phone collection not found with ID: {$phoneCollectionId}", 404);
            }

            if (!$phoneCollection->contractId) {
                throw new Exception("Contract ID not found for phone collection ID: {$phoneCollectionId}", 404);
            }

            $contractId = $phoneCollection->contractId;

            Log::info('Transferring contract to litigation', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
            ]);

            // Step 2: Call external API
            $url = self::BASE_URL . "/contracts/{$contractId}/transfer-litigation";

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->post($url);

            $data = $response->json();

            Log::info('Contract transferred to litigation', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'status' => $response->status(),
            ]);

            // Return exact response from external API
            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to transfer contract to litigation', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
