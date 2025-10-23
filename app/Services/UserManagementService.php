<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class UserManagementService
{
    private const BASE_URL = 'https://users-ms.vnapp.xyz';
    private const CACHE_KEY = 'duty_roster_eligible_users';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get eligible users for duty roster (with cache)
     * Users must have:
     * - call-processing permission in Auth Service
     * - isActive = true in local DB
     * - Belong to "Call Collection" team
     *
     * @param string $accessToken
     * @return array
     */
    public function getEligibleUsersForDutyRoster(string $accessToken): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($accessToken) {
            return $this->fetchEligibleUsers($accessToken);
        });
    }

    /**
     * Force refresh eligible users (bypass cache)
     *
     * @param string $accessToken
     * @return array
     */
    public function refreshEligibleUsers(string $accessToken): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getEligibleUsersForDutyRoster($accessToken);
    }

    /**
     * Fetch eligible users from Auth Service
     *
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    private function fetchEligibleUsers(string $accessToken): array
    {
        try {
            Log::info('Fetching eligible users for duty roster');

            // Step 1: Get "Call Collection" team ID
            $teamId = $this->getCallCollectionTeamId($accessToken);

            if (!$teamId) {
                throw new Exception('Call Collection team not found in Auth Service');
            }

            // Step 2: Get all users in "Call Collection" team
            $usersResponse = $this->getUsersByTeam($accessToken, $teamId);

            // Step 3: Filter users with "call-processing" permission
            $eligibleAuthUsers = collect($usersResponse)
                ->filter(function ($item) {
                    $permissions = $item['permissions'] ?? [];
                    return in_array('call-processing', $permissions);
                })
                ->values();

            Log::info('Filtered users with call-processing permission', [
                'total_team_users' => count($usersResponse),
                'eligible_users' => $eligibleAuthUsers->count(),
            ]);

            // Step 4: Cross-check with local DB (isActive = true)
            $authUserIds = $eligibleAuthUsers->pluck('user.user_id')->toArray();

            $activeLocalUsers = User::whereIn('authUserId', $authUserIds)
                ->where('isActive', true)
                ->get()
                ->keyBy('authUserId');

            Log::info('Cross-checked with local DB', [
                'auth_user_count' => count($authUserIds),
                'active_local_count' => $activeLocalUsers->count(),
            ]);

            // Step 5: Merge data from both sources
            $finalUsers = $eligibleAuthUsers
                ->filter(function ($item) use ($activeLocalUsers) {
                    // Only include if user exists in local DB and isActive = true
                    return $activeLocalUsers->has($item['user']['user_id']);
                })
                ->map(function ($item) use ($activeLocalUsers) {
                    $authUser = $item['user'];
                    $localUser = $activeLocalUsers->get($authUser['user_id']);

                    return [
                        // Local DB info
                        'id' => $localUser->id,
                        'authUserId' => $localUser->authUserId,
                        'isActive' => $localUser->isActive,
                        'lastLoginAt' => $localUser->lastLoginAt?->format('Y-m-d H:i:s'),

                        // Auth Service info
                        'username' => $authUser['username'],
                        'email' => $authUser['email'],
                        'userFullName' => $authUser['user_full_name'],
                        'empNo' => $authUser['emp_no'],
                        'phoneNo' => $authUser['phone_no'],
                        'extensionNo' => $authUser['ext_no'], // ✅ Extension number

                        // Roles & Permissions
                        'roles' => $item['roles'] ?? [],
                        'permissions' => $item['permissions'] ?? [],
                    ];
                })
                ->values()
                ->toArray();

            Log::info('Final eligible users for duty roster', [
                'count' => count($finalUsers),
            ]);

            return $finalUsers;

        } catch (Exception $e) {
            Log::error('Failed to fetch eligible users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get "Call Collection" team ID
     *
     * @param string $accessToken
     * @return int|null
     */
    private function getCallCollectionTeamId(string $accessToken): ?int
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->get(self::BASE_URL . '/api/v1/teams');

        if (!$response->successful()) {
            throw new Exception('Failed to fetch teams from Auth Service');
        }

        $data = $response->json();
        $teams = $data['data']['data'] ?? [];

        foreach ($teams as $team) {
            if (strcasecmp($team['name'], 'Call Collection') === 0) {
                return $team['team_id'];
            }
        }

        return null;
    }

    /**
     * Get users by team ID
     *
     * @param string $accessToken
     * @param int $teamId
     * @return array
     */
    private function getUsersByTeam(string $accessToken, int $teamId): array
    {
        Log::info('Fetching users by team', [
            'team_id' => $teamId,
        ]);

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Team-Id' => (string)$teamId,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->get(self::BASE_URL . '/api/v1/users');

        if (!$response->successful()) {
            throw new Exception('Failed to fetch users from Auth Service');
        }

        $data = $response->json();

        if (!isset($data['status']) || $data['status'] != 1) {
            throw new Exception('Invalid response from Auth Service /users endpoint');
        }

        return $data['data'] ?? [];
    }
}
