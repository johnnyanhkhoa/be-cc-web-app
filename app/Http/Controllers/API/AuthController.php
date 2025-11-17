<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Login user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // Validate input
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            $email = $request->input('email');
            $password = $request->input('password');

            // Call new login API (returns: current_user, access_token, token_type, expires_at)
            $authData = $this->authService->login($email, $password);

            // Extract data from new API response
            $currentUser = $authData['current_user'];
            $userInfo = $currentUser['user'];
            $roles = $currentUser['roles'] ?? [];
            $permissions = $currentUser['permissions'] ?? [];

            $accessToken = $authData['access_token'];
            $tokenType = $authData['token_type'];
            $expiresAt = $authData['expires_at'];

            // Calculate expires_in (seconds) from expires_at
            $expiresIn = null;
            if ($expiresAt) {
                try {
                    $expiresAtTime = \Carbon\Carbon::parse($expiresAt);
                    $expiresIn = $expiresAtTime->diffInSeconds(now());
                } catch (\Exception $e) {
                    Log::warning('Failed to parse expires_at', ['expires_at' => $expiresAt]);
                }
            }

            // Sync or update user in local database
            $localUser = User::updateOrCreate(
                ['authUserId' => $userInfo['user_id']],
                [
                    'username' => $userInfo['username'],
                    'email' => $userInfo['email'],
                    'userFullName' => $userInfo['user_full_name'],
                    'extensionNo' => $userInfo['ext_no'],
                    'lastLoginAt' => now(),
                    'isActive' => true,
                ]
            );

            Log::info('User logged in successfully', [
                'auth_user_id' => $userInfo['user_id'],
                'username' => $userInfo['username'],
                'email' => $userInfo['email'],
                'local_user_id' => $localUser->id,
                'roles' => $roles,
                'permissions' => $permissions,
            ]);

            // Return response in OLD format (without refresh_token)
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $localUser->id,
                        'authUserId' => (string)$localUser->authUserId,
                        'email' => $localUser->email,
                        'username' => $localUser->username,
                        'userFullName' => $localUser->userFullName,
                        'extensionNo' => $localUser->extensionNo,
                        'isActive' => $localUser->isActive,
                        'lastLoginAt' => $localUser->lastLoginAt?->utc()->format('Y-m-d\TH:i:s.u\Z'),
                    ],
                    'external_user' => [
                        'user_id' => $userInfo['user_id'],
                        'old_user_id' => $userInfo['old_user_id'] ?? null,
                        'username' => $userInfo['username'],
                        'user_full_name' => $userInfo['user_full_name'],
                        'emp_no' => $userInfo['emp_no'],
                        'email' => $userInfo['email'],
                        'phone_no' => $userInfo['phone_no'],
                        'ext_no' => $userInfo['ext_no'],
                        'created_at' => null,
                        'updated_at' => null,
                    ],
                    'auth' => [
                        'token_type' => $tokenType,
                        'access_token' => $accessToken,
                        'expires_in' => $expiresIn,
                    ],
                    'roles' => $roles,              // ← THÊM: Roles từ API mới
                    'permissions' => $permissions,  // ← THÊM: Permissions từ API mới
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Login failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Authentication failed'
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Logout user from external authentication
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Get access token from Authorization header
            $authHeader = $request->header('Authorization');

            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token is required',
                    'error' => 'Missing or invalid Authorization header'
                ], 401);
            }

            $accessToken = substr($authHeader, 7); // Remove "Bearer " prefix

            Log::info('Logout attempt', [
                'token_preview' => substr($accessToken, 0, 20) . '...'
            ]);

            // Call external logout API
            $logoutResponse = $this->authService->logout($accessToken);

            Log::info('Logout successful', [
                'message' => $logoutResponse['message'] ?? 'Logged out'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logout successful',
                'data' => $logoutResponse
            ], 200);

        } catch (Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Get current authenticated user info
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            // Get access token from Authorization header
            $authHeader = $request->header('Authorization');

            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token is required',
                    'error' => 'Missing or invalid Authorization header'
                ], 401);
            }

            $accessToken = substr($authHeader, 7); // Remove "Bearer " prefix

            // Get current user info from external API v2
            $userResponse = $this->authService->getUserInfo($accessToken);
            $user = $userResponse['data'];

            // Try to find local user
            $localUser = User::where('authUserId', $user['user_id'])->first();

            return response()->json([
                'success' => true,
                'message' => 'User info retrieved successfully',
                'data' => [
                    'local_user' => $localUser ? [
                        'id' => $localUser->id,
                        'authUserId' => $localUser->authUserId,
                        'email' => $localUser->email,
                        'username' => $localUser->username,
                        'userFullName' => $localUser->userFullName,
                        'extensionNo' => $localUser->extensionNo, // NEW
                        'isActive' => $localUser->isActive,
                        'lastLoginAt' => $localUser->lastLoginAt,
                    ] : null,
                    'external_user' => [
                        'user_id' => $user['user_id'],
                        'old_user_id' => $user['old_user_id'],
                        'username' => $user['username'],
                        'user_full_name' => $user['user_full_name'],
                        'emp_no' => $user['emp_no'],
                        'email' => $user['email'],
                        'phone_no' => $user['phone_no'],
                        'ext_no' => $user['ext_no'],
                        'created_at' => $user['created_at'],
                        'updated_at' => $user['updated_at'],
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Get user info failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get user info',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Check if user is allowed to access a specific team
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAllow(Request $request): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'teamName' => ['required', 'string', 'max:255'],
            ]);

            $teamName = $request->input('teamName');

            // Get access token from Authorization header
            $authHeader = $request->header('Authorization');

            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token is required',
                    'error' => 'Missing or invalid Authorization header'
                ], 401);
            }

            $accessToken = substr($authHeader, 7); // Remove "Bearer " prefix

            Log::info('Check-allow request received', [
                'team_name' => $teamName
            ]);

            // Step 1: Get all teams
            $teamsResponse = $this->authService->getAllTeams($accessToken);

            if (!isset($teamsResponse['data']['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch teams',
                    'error' => 'Invalid teams response format'
                ], 500);
            }

            $teams = $teamsResponse['data']['data'];

            // Step 2: Find team by name (case-insensitive)
            $foundTeam = null;
            foreach ($teams as $team) {
                if (strcasecmp($team['name'], $teamName) === 0) {
                    $foundTeam = $team;
                    break;
                }
            }

            if (!$foundTeam) {
                Log::warning('Team not found', [
                    'team_name' => $teamName,
                    'available_teams' => array_column($teams, 'name')
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Team not found',
                    'error' => "No team found with name: {$teamName}",
                    'availableTeams' => array_map(function($team) {
                        return [
                            'team_id' => $team['team_id'],
                            'name' => $team['name'],
                            'description' => $team['description']
                        ];
                    }, $teams)
                ], 404);
            }

            $teamId = $foundTeam['team_id'];

            Log::info('Team found', [
                'team_name' => $teamName,
                'team_id' => $teamId
            ]);

            // Step 3: Check permission for this team
            $permissionResponse = $this->authService->checkTeamPermission($accessToken, $teamId);

            Log::info('Permission check completed', [
                'team_name' => $teamName,
                'team_id' => $teamId,
                'response' => $permissionResponse
            ]);

            // Return exact response from is-allow API
            // Determine status code based on response
            $statusCode = 200;
            if (isset($permissionResponse['status']) && $permissionResponse['status'] == 0) {
                $statusCode = 403;
            }

            return response()->json($permissionResponse, $statusCode);

        } catch (Exception $e) {
            Log::error('Check-allow failed', [
                'team_name' => $request->input('teamName'),
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = 500;
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to check team permission',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], $statusCode);
        }
    }

    /**
     * Check user roles and permissions for a specific team
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkRole(Request $request): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'teamName' => ['required', 'string', 'max:255'],
            ]);

            $teamName = $request->input('teamName');

            // Get access token from Authorization header
            $authHeader = $request->header('Authorization');

            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token is required',
                    'error' => 'Missing or invalid Authorization header'
                ], 401);
            }

            $accessToken = substr($authHeader, 7); // Remove "Bearer " prefix

            Log::info('Check role request received', [
                'team_name' => $teamName
            ]);

            // Step 1: Get all teams to find teamId by teamName
            $teamsResponse = $this->authService->getAllTeams($accessToken);

            if (!isset($teamsResponse['data']['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch teams',
                    'error' => 'Invalid teams response format'
                ], 500);
            }

            $teams = $teamsResponse['data']['data'];

            // Step 2: Find team by name (case-insensitive)
            $foundTeam = null;
            foreach ($teams as $team) {
                if (strcasecmp($team['name'], $teamName) === 0) {
                    $foundTeam = $team;
                    break;
                }
            }

            if (!$foundTeam) {
                Log::warning('Team not found for role check', [
                    'team_name' => $teamName,
                    'available_teams' => array_column($teams, 'name')
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Team not found',
                    'error' => "No team found with name: {$teamName}",
                    'availableTeams' => array_map(function($team) {
                        return [
                            'team_id' => $team['team_id'],
                            'name' => $team['name'],
                            'description' => $team['description']
                        ];
                    }, $teams)
                ], 404);
            }

            $teamId = $foundTeam['team_id'];

            Log::info('Team found for role check', [
                'team_name' => $teamName,
                'team_id' => $teamId
            ]);

            // Step 3: Get user roles and permissions for this team
            $rolesResponse = $this->authService->getUserRolesByTeam($accessToken, $teamId);

            if (!isset($rolesResponse['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch roles',
                    'error' => 'Invalid response format'
                ], 500);
            }

            $data = $rolesResponse['data'];

            Log::info('User roles retrieved successfully', [
                'team_name' => $teamName,
                'team_id' => $teamId,
                'roles' => $data['roles'] ?? [],
                'permissions' => $data['permissions'] ?? []
            ]);

            // Return response without "user" field
            return response()->json([
                'status' => $rolesResponse['status'],
                'data' => [
                    'roles' => $data['roles'] ?? [],
                    'permissions' => $data['permissions'] ?? []
                ],
                'message' => $rolesResponse['message'] ?? ''
            ], 200);

        } catch (Exception $e) {
            Log::error('Check role failed', [
                'team_name' => $request->input('teamName'),
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = 500;
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to check user roles',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], $statusCode);
        }
    }
}
