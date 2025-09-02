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
use App\Jobs\WriteLogLogin;
use App\Services\GetDeviceInfoService;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private GetDeviceInfoService $getDeviceInfo
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
                ['auth_user_id' => $authUserId],
                [
                    'email' => $user['email'],
                    'username' => $user['username'],
                    'user_full_name' => $user['user_full_name'],
                    'is_active' => true,
                ]
            );

            // Step 4: Update last login timestamp
            $localUser->updateLastLogin();

            // Step 4.5: Track user login asynchronously
            try {
                $deviceData = $this->getDeviceInfo->getDeviceData(
                    $request,
                    4, // teamId fixed value
                    $user['email'],
                    $user['username']
                );

                $deviceData['user_id'] = $localUser->id;
                WriteLogLogin::dispatch($deviceData);

                Log::info('User tracking dispatched', [
                    'local_user_id' => $localUser->id,
                    'device_data_keys' => array_keys($deviceData)
                ]);
            } catch (Exception $trackingException) {
                // Don't fail login if tracking fails
                Log::warning('User tracking failed but login continues', [
                    'local_user_id' => $localUser->id,
                    'tracking_error' => $trackingException->getMessage()
                ]);
            }

            Log::info('Login successful', [
                'local_user_id' => $localUser->id,
                'auth_user_id' => $authUserId
            ]);

            // Step 5: Return success response
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $localUser->id,
                        'auth_user_id' => $localUser->auth_user_id,
                        'email' => $localUser->email,
                        'username' => $localUser->username,
                        'user_full_name' => $localUser->user_full_name,
                        'is_active' => $localUser->is_active,
                        'last_login_at' => $localUser->last_login_at,
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
            $localUser = User::where('auth_user_id', $user['user_id'])->first();

            return response()->json([
                'success' => true,
                'message' => 'User info retrieved successfully',
                'data' => [
                    'local_user' => $localUser ? [
                        'id' => $localUser->id,
                        'auth_user_id' => $localUser->auth_user_id,
                        'email' => $localUser->email,
                        'username' => $localUser->username,
                        'user_full_name' => $localUser->user_full_name,
                        'is_active' => $localUser->is_active,
                        'last_login_at' => $localUser->last_login_at,
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
}
