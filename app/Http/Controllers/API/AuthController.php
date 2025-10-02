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
     * Login user via external authentication
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Get credentials from request
            $username = $request->validated()['username'];
            $password = $request->validated()['password'];

            Log::info('Login attempt', ['username' => $username]);

            // Step 1: Authenticate with external auth API
            $authResponse = $this->authService->login($username, $password);

            Log::info('External authentication successful', [
                'username' => $username,
                'token_type' => $authResponse['token_type'] ?? 'unknown'
            ]);

            // Step 2: Get current user info using access token
            $userData = $this->authService->getUserInfo($authResponse['access_token']);
            $user = $userData['user'];
            $authUserId = $user['user_id'];

            Log::info('User info retrieved', [
                'user_id' => $authUserId,
                'username' => $user['username'] ?? 'unknown',
                'email' => $user['email'] ?? 'unknown'
            ]);

            // Step 3: Create or update local user
            $localUser = User::updateOrCreate(
                ['authUserId' => $authUserId],
                [
                    'email' => $user['email'],
                    'username' => $user['username'],
                    'userFullName' => $user['user_full_name'], // Fixed mapping
                    'isActive' => true, // Fixed field name
                ]
            );

            // Step 4: Update last login timestamp
            $localUser->updateLastLogin();

            Log::info('Login successful', [
                'local_user_id' => $localUser->id,
                'authUserId' => $authUserId
            ]);

            // Step 5: Return success response
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $localUser->id,
                        'authUserId' => $localUser->authUserId,
                        'email' => $localUser->email,
                        'username' => $localUser->username,
                        'userFullName' => $localUser->userFullName,
                        'isActive' => $localUser->isActive,
                        'lastLoginAt' => $localUser->lastLoginAt,
                    ],
                    'external_user' => [
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'full_name' => $user['user_full_name'],
                        'emp_no' => $user['emp_no'],
                        'roles' => $userData['roles'] ?? [],
                        'permissions' => $userData['permissions'] ?? [],
                    ],
                    'auth' => [
                        'token_type' => $authResponse['token_type'],
                        'access_token' => $authResponse['access_token'],
                        'refresh_token' => $authResponse['refresh_token'] ?? null,
                        'expires_in' => $authResponse['expires_in'],
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Login failed', [
                'username' => $request->validated()['username'] ?? 'unknown',
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            // Handle database constraint errors
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User data conflict. Please try again.',
                    'error' => 'Database constraint violation'
                ], 409);
            }

            // Handle external API errors
            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = 400;
            }

            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], $statusCode);
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

            // Get current user info from external API
            $userData = $this->authService->getUserInfo($accessToken);
            $user = $userData['user'];

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
                        'isActive' => $localUser->isActive,
                        'lastLoginAt' => $localUser->lastLoginAt,
                    ] : null,
                    'external_user' => [
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'full_name' => $user['user_full_name'],
                        'emp_no' => $user['emp_no'],
                        'roles' => $userData['roles'] ?? [],
                        'permissions' => $userData['permissions'] ?? [],
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
}
