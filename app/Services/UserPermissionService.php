<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class UserPermissionService
{
    private const BASE_URL = 'https://users-ms.vnapp.xyz';
    private const BEARER_TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjEsInJvbGUiOiJvZmZpY2VyIiwiaWF0IjoxNjk1MDAwMDAwLCJleHAiOjE2OTUwMDM2MDB9.4JkQJgQZk-G4WQG5Tn0P4cXx1G1v3U5Vv-Rg8zV2B3o';

    /**
     * Get all teams
     */
    private function getAllTeams(): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . self::BEARER_TOKEN,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->get(self::BASE_URL . '/api/v1/teams');

        if ($response->successful()) {
            $data = $response->json();
            return $data['data']['data'] ?? [];
        }

        return [];
    }

    /**
     * Find team ID by name
     */
    private function findTeamIdByName(string $teamName): ?int
    {
        $teams = $this->getAllTeams();

        foreach ($teams as $team) {
            if (strcasecmp($team['name'], $teamName) === 0) {
                return $team['team_id'];
            }
        }

        return null;
    }

    /**
     * Check if user is allowed to access team
     */
    public function checkUserAllowForTeam(User $user, string $teamName): bool
    {
        try {
            $teamId = $this->findTeamIdByName($teamName);

            if (!$teamId) {
                return false;
            }

            // Generate access token for this user (you should get real token)
            // For now, using static token
            $accessToken = self::BEARER_TOKEN;

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Team-Id' => (string)$teamId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get(self::BASE_URL . '/api/v1/is-allow');

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['auth']) && $data['auth'] === true;
            }

            return false;

        } catch (Exception $e) {
            Log::error('Failed to check user allow', [
                'user_id' => $user->authUserId,
                'team_name' => $teamName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user roles for team
     */
    public function getUserRolesForTeam(User $user, string $teamName): array
    {
        try {
            $teamId = $this->findTeamIdByName($teamName);

            if (!$teamId) {
                return [];
            }

            // Generate access token for this user
            $accessToken = self::BEARER_TOKEN;

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Team-Id' => (string)$teamId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get(self::BASE_URL . '/api/v1/current-user');

            if ($response->successful()) {
                $data = $response->json();
                return $data['data']['roles'] ?? [];
            }

            return [];

        } catch (Exception $e) {
            Log::error('Failed to get user roles', [
                'user_id' => $user->authUserId,
                'team_name' => $teamName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if user has specific role
     */
    public function userHasRole(User $user, string $teamName, string $roleName): bool
    {
        $roles = $this->getUserRolesForTeam($user, $teamName);

        foreach ($roles as $role) {
            if (strcasecmp($role, $roleName) === 0) {
                return true;
            }
        }

        return false;
    }
}
