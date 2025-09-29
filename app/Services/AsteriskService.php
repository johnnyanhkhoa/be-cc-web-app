<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AsteriskService
{
    private const BASE_URL = 'https://asterisk-ms.vnapp.xyz/api';
    private const BEARER_TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjEsInJvbGUiOiJvZmZpY2VyIiwiaWF0IjoxNjk1MDAwMDAwLCJleHAiOjE2OTUwMDM2MDB9.4JkQJgQZk-G4WQG5Tn0P4cXx1G1v3U5Vv-Rg8zV2B3o';
    private const MODULE_NAME = 'phone_collection';
    private const COMPANY = 'r2o';

    /**
     * Initiate a voice call through Asterisk
     *
     * @param string $phoneExtension
     * @param string $phoneNo
     * @param string $caseId
     * @param string $username
     * @param int $userId
     * @return array
     * @throws Exception
     */
    public function initiateCall(
        string $phoneExtension,
        string $phoneNo,
        string $caseId,
        string $username,
        int $userId
    ): array {
        try {
            $payload = [
                'phoneExtension' => $phoneExtension,
                'phoneNo' => $phoneNo,
                'moduleName' => self::MODULE_NAME,
                'caseId' => $caseId,
                'username' => $username,
                'userId' => $userId,
                'company' => self::COMPANY,
            ];

            Log::info('Initiating Asterisk voice call', [
                'phone_extension' => $phoneExtension,
                'phone_no' => $phoneNo,
                'case_id' => $caseId,
                'username' => $username,
                'user_id' => $userId,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . self::BEARER_TOKEN,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(self::BASE_URL . '/voice-call', $payload);

            // Log response
            Log::info('Asterisk API response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Voice call initiated successfully', [
                    'phone_no' => $phoneNo,
                    'case_id' => $caseId,
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                    'status_code' => $response->status(),
                ];
            }

            // Handle error response
            $errorData = $response->json();

            Log::warning('Asterisk API call failed', [
                'status' => $response->status(),
                'error' => $errorData,
                'phone_no' => $phoneNo,
            ]);

            throw new Exception(
                $errorData['message'] ?? 'Failed to initiate voice call',
                $response->status()
            );

        } catch (Exception $e) {
            Log::error('Asterisk API connection error', [
                'phone_no' => $phoneNo,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw new Exception(
                'Unable to connect to Asterisk service: ' . $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get call status (if Asterisk API provides this endpoint)
     *
     * @param string $callId
     * @return array
     */
    public function getCallStatus(string $callId): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . self::BEARER_TOKEN,
                    'Accept' => 'application/json',
                ])
                ->get(self::BASE_URL . '/voice-call/' . $callId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            throw new Exception('Failed to get call status', $response->status());

        } catch (Exception $e) {
            Log::error('Failed to get call status', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
