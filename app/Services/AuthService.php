<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AuthService
{
    private const BASE_URL = 'https://users-ms.vnapp.xyz';
    private const LOGIN_ENDPOINT = '/oauth/token';

    // Fixed credentials as per requirements
    private const GRANT_TYPE = 'password';
    private const CLIENT_ID = '0197d55c-7338-7249-b9ca-6be67b78b007';
    private const CLIENT_SECRET = '3B17cBmoTaGdeGqTxFQXb96OplIEm0jfAMicuxtR';

    /**
     * Authenticate user with external auth API
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws Exception
     */
    public function login(string $username, string $password): array
    {
        try {
            $loginData = [
                'username' => trim($username),
                'password' => $password,
                'grant_type' => self::GRANT_TYPE,
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
            ];

            Log::info('Attempting external authentication', [
                'username' => $username,
                'endpoint' => self::BASE_URL . self::LOGIN_ENDPOINT
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(self::BASE_URL . self::LOGIN_ENDPOINT, $loginData);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('External authentication successful', [
                    'username' => $username,
                    'token_type' => $data['token_type'] ?? 'unknown',
                    'expires_in' => $data['expires_in'] ?? 0
                ]);

                return $data;
            }

            // Handle authentication failure
            $errorData = $response->json();
            Log::warning('External authentication failed', [
                'username' => $username,
                'status' => $response->status(),
                'error' => $errorData
            ]);

            throw new Exception(
                $errorData['message'] ?? 'Authentication failed',
                $response->status()
            );

        } catch (Exception $e) {
            Log::error('External API connection error', [
                'username' => $username,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            throw new Exception(
                'Unable to connect to authentication service: ' . $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get current user info using access token (API v2)
     *
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            Log::info('Attempting to get current user info from v2 API', [
                'endpoint' => self::BASE_URL . '/api/v2/current-user',
                'token_preview' => substr($accessToken, 0, 20) . '...'
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get(self::BASE_URL . '/api/v2/current-user');

            Log::info('Current user API v2 response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Validate response structure for v2
                if (isset($data['status']) && $data['status'] == 1 && isset($data['data'])) {
                    return $data; // Return full response including status, data, message
                }

                throw new Exception('Invalid response format from current-user v2 endpoint');
            }

            throw new Exception('Failed to get user info - Status: ' . $response->status() . ' Body: ' . $response->body(), $response->status());

        } catch (Exception $e) {
            Log::error('Failed to get current user info from v2', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            throw $e;
        }
    }

    /**
     * Logout user from external auth API
     *
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function logout(string $accessToken): array
    {
        try {
            Log::info('Attempting to logout user', [
                'endpoint' => self::BASE_URL . '/api/v1/logout',
                'token_preview' => substr($accessToken, 0, 20) . '...'
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(self::BASE_URL . '/api/v1/logout');

            Log::info('Logout API response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Logout successful', [
                    'message' => $data['message'] ?? 'Logged out successfully'
                ]);

                return $data;
            }

            throw new Exception('Failed to logout - Status: ' . $response->status() . ' Body: ' . $response->body(), $response->status());

        } catch (Exception $e) {
            Log::error('Logout error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            throw $e;
        }
    }

    /**
     * Check if user has permission for specific action
     *
     * @param string $accessToken
     * @param string $teamId
     * @return array
     * @throws Exception
     */
    public function checkPermission(string $accessToken, string $teamId): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Team-Id' => $teamId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(self::BASE_URL . '/api/v1/is-allow');

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Permission check failed', $response->status());

        } catch (Exception $e) {
            Log::error('Permission check error', [
                'team_id' => $teamId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get all teams
     *
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function getAllTeams(string $accessToken): array
    {
        try {
            Log::info('Fetching all teams from external API');

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get(self::BASE_URL . '/api/v1/teams');

            Log::info('Teams API response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] == 1 && isset($data['data']['data'])) {
                    return $data;
                }

                throw new Exception('Invalid response format from teams endpoint');
            }

            throw new Exception('Failed to fetch teams - Status: ' . $response->status(), $response->status());

        } catch (Exception $e) {
            Log::error('Failed to fetch teams', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            throw $e;
        }
    }

    /**
     * Check if user is allowed to access a team
     *
     * @param string $accessToken
     * @param int $teamId
     * @return array
     * @throws Exception
     */
    public function checkTeamPermission(string $accessToken, int $teamId): array
    {
        try {
            Log::info('Checking team permission', [
                'team_id' => $teamId
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Team-Id' => (string)$teamId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get(self::BASE_URL . '/api/v1/is-allow');

            Log::info('is-allow API response', [
                'status' => $response->status(),
                'team_id' => $teamId,
                'body' => $response->body()
            ]);

            if ($response->successful() || $response->status() === 403) {
                // Return response as-is (both success and permission denied)
                return $response->json();
            }

            throw new Exception('Failed to check permission - Status: ' . $response->status(), $response->status());

        } catch (Exception $e) {
            Log::error('Failed to check team permission', [
                'team_id' => $teamId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get user roles and permissions by team
     *
     * @param string $accessToken
     * @param int $teamId
     * @return array
     * @throws Exception
     */
    public function getUserRolesByTeam(string $accessToken, int $teamId): array
    {
        try {
            Log::info('Getting user roles and permissions', [
                'team_id' => $teamId
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Team-Id' => (string)$teamId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get(self::BASE_URL . '/api/v1/current-user');

            Log::info('User roles API response', [
                'status' => $response->status(),
                'team_id' => $teamId,
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Validate response structure
                if (isset($data['status']) && $data['status'] == 1 && isset($data['data'])) {
                    return $data;
                }

                throw new Exception('Invalid response format from current-user endpoint');
            }

            throw new Exception('Failed to get user roles - Status: ' . $response->status(), $response->status());

        } catch (Exception $e) {
            Log::error('Failed to get user roles', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
