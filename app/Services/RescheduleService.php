<?php

namespace App\Services;

use App\Models\TblCcPhoneCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class RescheduleService
{
    // private const BASE_URL = 'https://maximus-staging.vnapp.xyz/api/v1/cc';
    private const BASE_URL = 'https://maximus.vnapp.xyz/api/v1/cc';
    private const API_KEY = 't03JN3y8L12gzVbuLuorjwBAHgVAkkY6QOvJkP6m';

    /**
     * Get proposed schedules by phoneCollectionId
     *
     * @param int $phoneCollectionId
     * @return array
     * @throws Exception
     */
    public function getProposedSchedules(int $phoneCollectionId): array
    {
        try {
            // Get contractId from phone collection
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                throw new Exception("Phone collection not found with ID: {$phoneCollectionId}", 404);
            }

            if (!$phoneCollection->contractId) {
                throw new Exception("Contract ID not found for phone collection ID: {$phoneCollectionId}", 404);
            }

            $contractId = $phoneCollection->contractId;

            Log::info('Getting proposed schedules', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
            ]);

            // Call external API
            $url = self::BASE_URL . "/contracts/{$contractId}/rescheduled-payments/calculate";

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->get($url);

            $data = $response->json();

            Log::info('Proposed schedules retrieved', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'status' => $response->status(),
            ]);

            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get proposed schedules', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Post reschedule by phoneCollectionId
     *
     * @param int $phoneCollectionId
     * @param array $rescheduleData
     * @return array
     * @throws Exception
     */
    public function postReschedule(int $phoneCollectionId, array $rescheduleData): array
    {
        try {
            // Get contractId from phone collection
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                throw new Exception("Phone collection not found with ID: {$phoneCollectionId}", 404);
            }

            if (!$phoneCollection->contractId) {
                throw new Exception("Contract ID not found for phone collection ID: {$phoneCollectionId}", 404);
            }

            $contractId = $phoneCollection->contractId;

            Log::info('Posting reschedule', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'reschedule_data' => $rescheduleData,
            ]);

            // Call external API
            $url = self::BASE_URL . "/contracts/{$contractId}/reschedule";

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->post($url, $rescheduleData);

            $data = $response->json();

            Log::info('Reschedule posted', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'status' => $response->status(),
            ]);

            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to post reschedule', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get signature link by phoneCollectionId
     *
     * @param int $phoneCollectionId
     * @return array
     * @throws Exception
     */
    public function getSignatureLink(int $phoneCollectionId): array
    {
        try {
            // Get contractId from phone collection
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                throw new Exception("Phone collection not found with ID: {$phoneCollectionId}", 404);
            }

            if (!$phoneCollection->contractId) {
                throw new Exception("Contract ID not found for phone collection ID: {$phoneCollectionId}", 404);
            }

            $contractId = $phoneCollection->contractId;

            Log::info('Getting signature link', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
            ]);

            // Call external API
            $url = self::BASE_URL . "/contracts/{$contractId}/rescheduling/signature-link";

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->get($url);

            $data = $response->json();

            Log::info('Signature link retrieved', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'status' => $response->status(),
            ]);

            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get signature link', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create new signature link by phoneCollectionId
     *
     * @param int $phoneCollectionId
     * @param array $signatureData
     * @return array
     * @throws Exception
     */
    public function createSignatureLink(int $phoneCollectionId, array $signatureData): array
    {
        try {
            // Get contractId from phone collection
            $phoneCollection = TblCcPhoneCollection::find($phoneCollectionId);

            if (!$phoneCollection) {
                throw new Exception("Phone collection not found with ID: {$phoneCollectionId}", 404);
            }

            if (!$phoneCollection->contractId) {
                throw new Exception("Contract ID not found for phone collection ID: {$phoneCollectionId}", 404);
            }

            $contractId = $phoneCollection->contractId;

            Log::info('Creating signature link', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'signature_data' => $signatureData,
            ]);

            // Call external API
            $url = self::BASE_URL . "/contracts/{$contractId}/rescheduling/signature-link";

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => self::API_KEY,
                ])
                ->post($url, $signatureData);

            $data = $response->json();

            Log::info('Signature link created', [
                'phone_collection_id' => $phoneCollectionId,
                'contract_id' => $contractId,
                'status' => $response->status(),
            ]);

            return [
                'status_code' => $response->status(),
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error('Failed to create signature link', [
                'phone_collection_id' => $phoneCollectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
