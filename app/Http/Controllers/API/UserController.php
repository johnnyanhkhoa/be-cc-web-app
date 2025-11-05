<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DutyRoster;
use App\Services\UserPermissionService;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\TblCcUserLevel;  // ← THÊM
use App\Services\UserLevelService;  // ← THÊM

class UserController extends Controller
{
    protected $permissionService;
    protected $userManagementService;
    protected $levelService;  // ← THÊM

    public function __construct(
        UserPermissionService $permissionService,
        UserManagementService $userManagementService,
        UserLevelService $levelService  // ← THÊM
    ) {
        $this->permissionService = $permissionService;
        $this->userManagementService = $userManagementService;
        $this->levelService = $levelService;  // ← THÊM
    }

    /**
     * Get available users for assignment on a specific date
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableUsersForAssign(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date'
            ]);

            $date = $request->input('date');
            $teamName = 'Call Collection';
            $requiredRole = 'Officer';

            Log::info('Getting available users for assignment', [
                'date' => $date,
                'team_name' => $teamName,
                'required_role' => $requiredRole
            ]);

            // Step 1: Get all active users
            $activeUsers = User::where('isActive', true)->get();

            Log::info('Found active users', [
                'count' => $activeUsers->count()
            ]);

            // Step 2: Get users who are on duty roster for this date
            $dutyRosterUsers = DutyRoster::with('user')
                ->where('work_date', $date)
                ->where('is_working', true)
                ->get()
                ->pluck('user')
                ->filter()
                ->keyBy('authUserId');

            Log::info('Found users in duty roster', [
                'count' => $dutyRosterUsers->count(),
                'auth_user_ids' => $dutyRosterUsers->pluck('authUserId')->toArray()
            ]);

            // Step 3: Filter users by conditions
            $availableUsers = [];

            foreach ($activeUsers as $user) {
                // Check if user is in duty roster for this date
                if (!$dutyRosterUsers->has($user->authUserId)) {
                    Log::debug('User not in duty roster', [
                        'auth_user_id' => $user->authUserId,
                        'username' => $user->username
                    ]);
                    continue;
                }

                // Check if user is allowed for Call Collection team
                $isAllowed = $this->permissionService->checkUserAllowForTeam($user, $teamName);

                if (!$isAllowed) {
                    Log::debug('User not allowed for team', [
                        'auth_user_id' => $user->authUserId,
                        'username' => $user->username,
                        'team_name' => $teamName
                    ]);
                    continue;
                }

                // Check if user has Officer role
                $hasRole = $this->permissionService->userHasRole($user, $teamName, $requiredRole);

                if (!$hasRole) {
                    Log::debug('User does not have required role', [
                        'auth_user_id' => $user->authUserId,
                        'username' => $user->username,
                        'required_role' => $requiredRole
                    ]);
                    continue;
                }

                // User passed all checks
                $availableUsers[] = [
                    'authUserId' => $user->authUserId,
                    'email' => $user->email,
                    'username' => $user->username,
                    'userFullName' => $user->userFullName,
                    'date' => $date
                ];

                Log::info('User qualified for assignment', [
                    'auth_user_id' => $user->authUserId,
                    'username' => $user->username
                ]);
            }

            Log::info('Available users for assignment found', [
                'date' => $date,
                'total_available' => count($availableUsers)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Available users retrieved successfully',
                'data' => [
                    'date' => $date,
                    'team_name' => $teamName,
                    'required_role' => $requiredRole,
                    'users' => $availableUsers,
                    'total_count' => count($availableUsers)
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get available users for assignment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Debug: Check user qualification step by step
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function debugAvailableUsers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date'
            ]);

            $date = $request->input('date');
            $teamName = 'Call Collection';
            $requiredRole = 'Officer';

            $debugInfo = [];

            // Step 1: Get all active users
            $activeUsers = User::where('isActive', true)->get();
            $debugInfo['step1_active_users'] = [
                'count' => $activeUsers->count(),
                'users' => $activeUsers->map(fn($u) => [
                    'authUserId' => $u->authUserId,
                    'username' => $u->username,
                    'email' => $u->email,
                    'isActive' => $u->isActive
                ])->toArray()
            ];

            // Step 2: Get duty roster users for this date
            $dutyRosterRecords = DutyRoster::with('user')
                ->where('work_date', $date)
                ->where('is_working', true)
                ->get();

            $dutyRosterUsers = $dutyRosterRecords
                ->pluck('user')
                ->filter()
                ->keyBy('authUserId');

            $debugInfo['step2_duty_roster'] = [
                'count' => $dutyRosterUsers->count(),
                'users' => $dutyRosterUsers->map(fn($u) => [
                    'authUserId' => $u->authUserId,
                    'username' => $u->username,
                    'email' => $u->email
                ])->values()->toArray(),
                'raw_records' => $dutyRosterRecords->map(fn($dr) => [
                    'id' => $dr->id,
                    'work_date' => $dr->work_date,
                    'user_id' => $dr->user_id,
                    'is_working' => $dr->is_working,
                    'user_auth_user_id' => $dr->user ? $dr->user->authUserId : null,
                    'user_username' => $dr->user ? $dr->user->username : null
                ])->toArray()
            ];

            // Step 3: Check each user
            $userChecks = [];

            foreach ($activeUsers as $user) {
                $check = [
                    'authUserId' => $user->authUserId,
                    'username' => $user->username,
                    'email' => $user->email,
                    'checks' => []
                ];

                // Check 1: In duty roster?
                $inDutyRoster = $dutyRosterUsers->has($user->authUserId);
                $check['checks']['in_duty_roster'] = $inDutyRoster;

                if (!$inDutyRoster) {
                    $check['failed_at'] = 'duty_roster';
                    $userChecks[] = $check;
                    continue;
                }

                // Check 2: Is allowed for team?
                $isAllowed = $this->permissionService->checkUserAllowForTeam($user, $teamName);
                $check['checks']['is_allowed'] = $isAllowed;

                if (!$isAllowed) {
                    $check['failed_at'] = 'team_permission';
                    $userChecks[] = $check;
                    continue;
                }

                // Check 3: Has Officer role?
                $roles = $this->permissionService->getUserRolesForTeam($user, $teamName);
                $check['checks']['roles'] = $roles;
                $check['checks']['has_officer_role'] = in_array($requiredRole, $roles);

                if (!in_array($requiredRole, $roles)) {
                    $check['failed_at'] = 'role_check';
                    $userChecks[] = $check;
                    continue;
                }

                // Passed all checks
                $check['passed'] = true;
                $userChecks[] = $check;
            }

            $debugInfo['step3_user_checks'] = $userChecks;

            // Summary
            $debugInfo['summary'] = [
                'total_active_users' => $activeUsers->count(),
                'in_duty_roster' => collect($userChecks)->where('checks.in_duty_roster', true)->count(),
                'has_permission' => collect($userChecks)->where('checks.is_allowed', true)->count(),
                'has_officer_role' => collect($userChecks)->where('checks.has_officer_role', true)->count(),
                'passed_all_checks' => collect($userChecks)->where('passed', true)->count()
            ];

            return response()->json([
                'success' => true,
                'date' => $date,
                'debug_info' => $debugInfo
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get eligible users for duty roster
     * Returns ALL active users, with their level if assigned for this batch
     *
     * GET /api/users/eligible-for-duty-roster?batchId=1
     * GET /api/users/eligible-for-duty-roster?batchId=1&level=senior
     * GET /api/users/eligible-for-duty-roster?batchId=1&hasLevel=true (chỉ users có level)
     * GET /api/users/eligible-for-duty-roster?batchId=1&hasLevel=false (chỉ users chưa có level)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEligibleUsersForDutyRoster(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'batchId' => 'required|integer|min:1',
                'level' => 'nullable|string|in:team-leader,senior,mid-level,junior',
                'hasLevel' => 'nullable|in:true,false,1,0',  // Filter by có/chưa có level
                'isActive' => 'nullable|in:true,false,1,0',  // Filter by user active status
            ]);

            $batchId = $request->input('batchId');
            $levelFilter = $request->input('level');

            $hasLevelParam = $request->input('hasLevel');
            $hasLevel = $hasLevelParam === null ? null : filter_var($hasLevelParam, FILTER_VALIDATE_BOOLEAN);

            $isActiveParam = $request->input('isActive');
            $isActive = $isActiveParam === null ? true : filter_var($isActiveParam, FILTER_VALIDATE_BOOLEAN);

            Log::info('Getting eligible users for duty roster', [
                'batch_id' => $batchId,
                'level_filter' => $levelFilter,
                'has_level' => $hasLevel,
                'is_active' => $isActive
            ]);

            // ✅ Step 1: Get ALL active users (không phụ thuộc vào tbl_CcUserLevel)
            $query = User::where('isActive', $isActive);

            // Optional: Add permission check here if needed
            // $query->where(...)

            $users = $query->orderBy('userFullName', 'asc')->get();

            // ✅ Step 2: Get user levels for this batch
            $userLevelsQuery = TblCcUserLevel::where('batchId', $batchId)
                ->where('isActive', true);

            if ($levelFilter) {
                $userLevelsQuery->where('level', $levelFilter);
            }

            $userLevels = $userLevelsQuery->get()->keyBy('userId');

            // ✅ Step 3: Combine users with their levels
            $result = $users->map(function ($user) use ($userLevels, $batchId) {
                $userLevel = $userLevels->get($user->id);

                return [
                    'id' => $user->id,
                    'authUserId' => $user->authUserId,
                    'username' => $user->username,
                    'userFullName' => $user->userFullName,
                    'email' => $user->email,
                    'extensionNo' => $user->extensionNo,
                    'level' => $userLevel?->level ?? null,  // ✅ NULL nếu chưa có level
                    'batchId' => $batchId,
                    'hasLevel' => $userLevel !== null,  // ✅ Flag để biết có level chưa
                    'isActive' => $user->isActive,
                ];
            });

            // ✅ Step 4: Apply filters
            if ($levelFilter) {
                // Filter by specific level
                $result = $result->filter(function ($user) use ($levelFilter) {
                    return $user['level'] === $levelFilter;
                });
            }

            if ($hasLevel !== null) {
                // Filter by có/chưa có level
                $result = $result->filter(function ($user) use ($hasLevel) {
                    return $user['hasLevel'] === $hasLevel;
                });
            }

            $result = $result->values(); // Re-index array

            Log::info('Eligible users retrieved', [
                'total' => $result->count(),
                'with_level' => $result->where('hasLevel', true)->count(),
                'without_level' => $result->where('hasLevel', false)->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Eligible users retrieved successfully',
                'data' => [
                    'users' => $result,
                    'total' => $result->count(),
                    'summary' => [
                        'withLevel' => $result->where('hasLevel', true)->count(),
                        'withoutLevel' => $result->where('hasLevel', false)->count(),
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Failed to get eligible users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get eligible users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users with their levels
     *
     * GET /api/users
     * GET /api/users?batchId=1&level=senior&isActive=true (users ĐANG là senior)
     * GET /api/users?batchId=1&level=senior&isActive=false (users TỪNG là senior)
     * GET /api/users?batchId=1&level=senior (users ĐANG hoặc TỪNG là senior)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'batchId' => 'nullable|integer|min:1',
                'level' => 'nullable|string|in:team-leader,senior,mid-level,junior',
                'levelIsActive' => 'nullable|in:true,false,1,0',
                'isActive' => 'nullable|in:true,false,1,0',
                'search' => 'nullable|string',
            ]);

            $batchId = $request->query('batchId');
            $levelFilter = $request->query('level');

            // levelIsActive để filter level history
            $levelIsActiveParam = $request->query('levelIsActive');
            $levelIsActive = $levelIsActiveParam === null ? null : filter_var($levelIsActiveParam, FILTER_VALIDATE_BOOLEAN);

            // isActive để filter user status
            $userIsActiveParam = $request->query('isActive');
            $userIsActive = $userIsActiveParam === null ? null : filter_var($userIsActiveParam, FILTER_VALIDATE_BOOLEAN);

            $search = $request->query('search');

            Log::info('Getting all users with filters', [
                'batch_id' => $batchId,
                'level' => $levelFilter,
                'level_is_active' => $levelIsActive,
                'user_is_active' => $userIsActive,
                'search' => $search
            ]);

            // ✅ Build query
            $query = User::query();

            // Filter by user active status
            if ($userIsActive !== null) {
                $query->where('isActive', $userIsActive);
            }

            // Filter by search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('userFullName', 'ILIKE', "%{$search}%")
                    ->orWhere('username', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
                });
            }

            // ✅ Filter by level if batchId and level provided
            if ($batchId && $levelFilter) {
                $levelQuery = TblCcUserLevel::where('batchId', $batchId)
                    ->where('level', $levelFilter);

                // Filter by level active status
                if ($levelIsActive !== null) {
                    $levelQuery->where('isActive', $levelIsActive);
                }

                $userIdsWithLevel = $levelQuery->pluck('userId')->unique()->toArray();

                if (!empty($userIdsWithLevel)) {
                    $query->whereIn('id', $userIdsWithLevel);
                } else {
                    // No users found with this level
                    $query->whereRaw('1 = 0'); // Return empty result
                }
            }

            $users = $query->orderBy('userFullName', 'asc')->get();

            // ✅ Get user levels for specific batch if provided
            $userLevels = [];
            if ($batchId) {
                $levels = TblCcUserLevel::where('batchId', $batchId)
                    ->where('isActive', true)
                    ->get()
                    ->keyBy('userId');

                foreach ($levels as $userId => $levelRecord) {
                    $userLevels[$userId] = $levelRecord->level;
                }
            }

            // Format response
            $formattedUsers = $users->map(function($user) use ($batchId, $userLevels) {
                $data = [
                    'id' => $user->id,
                    'authUserId' => $user->authUserId,
                    'username' => $user->username,
                    'userFullName' => $user->userFullName,
                    'email' => $user->email,
                    'extensionNo' => $user->extensionNo,
                    'isActive' => $user->isActive,
                    'lastLoginAt' => $user->lastLoginAt?->format('Y-m-d H:i:s'),
                    'createdAt' => $user->createdAt?->format('Y-m-d H:i:s'),
                    'updatedAt' => $user->updatedAt?->format('Y-m-d H:i:s'),
                ];

                // Add current level if batchId provided
                if ($batchId) {
                    $data['currentLevel'] = $userLevels[$user->id] ?? null;
                    $data['batchId'] = $batchId;
                }

                return $data;
            });

            Log::info('Users retrieved successfully', [
                'total' => $users->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $formattedUsers,
                'total' => $users->count()
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Failed to get users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

   /**
     * Update user level for a specific batch
     *
     * PUT /api/users/{userId}/level
     *
     * Body: {
     *   "batchId": 1,
     *   "level": "senior",
     *   "updatedBy": 80
     * }
     */
    public function updateLevel(Request $request, int $userId): JsonResponse
    {
        try {
            $request->validate([
                'batchId' => 'required|integer|min:1',
                'level' => 'required|string|in:team-leader,senior,mid-level,junior',
                'updatedBy' => 'required|integer|exists:users,authUserId',
            ]);

            $batchId = $request->input('batchId');
            $level = $request->input('level');
            $updatedByAuthUserId = $request->input('updatedBy');

            // Get updater user
            $updaterUser = User::where('authUserId', $updatedByAuthUserId)->first();
            if (!$updaterUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Updater user not found',
                ], 404);
            }

            // Set level using service
            $userLevel = $this->levelService->setUserLevel(
                $userId,
                $batchId,
                $level,
                $updaterUser->id
            );

            return response()->json([
                'success' => true,
                'message' => 'User level updated successfully',
                'data' => [
                    'userLevelId' => $userLevel->userLevelId,
                    'userId' => $userLevel->userId,
                    'user' => [
                        'id' => $userLevel->user->id,
                        'authUserId' => $userLevel->user->authUserId,
                        'username' => $userLevel->user->username,
                        'userFullName' => $userLevel->user->userFullName,
                    ],
                    'batchId' => $userLevel->batchId,
                    'level' => $userLevel->level,
                    'isActive' => $userLevel->isActive,
                    'createdBy' => $userLevel->creator ? [
                        'id' => $userLevel->creator->id,
                        'username' => $userLevel->creator->username,
                        'userFullName' => $userLevel->creator->userFullName,
                    ] : null,
                    'createdAt' => $userLevel->createdAt->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to update user level', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch update user levels
     *
     * POST /api/users/batch-update-levels
     *
     * Body: {
     *   "batchId": 1,
     *   "updates": [
     *     { "userId": 7, "level": "senior" },
     *     { "userId": 5, "level": "junior" }
     *   ],
     *   "updatedBy": 80
     * }
     */
    public function batchUpdateLevels(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'batchId' => 'required|integer|min:1',
                'updates' => 'required|array|min:1',
                'updates.*.userId' => 'required|integer|exists:users,id',
                'updates.*.level' => 'required|string|in:team-leader,senior,mid-level,junior',
                'updatedBy' => 'required|integer|exists:users,authUserId',
            ]);

            $batchId = $request->input('batchId');
            $updates = $request->input('updates');
            $updatedByAuthUserId = $request->input('updatedBy');

            // Get updater user
            $updaterUser = User::where('authUserId', $updatedByAuthUserId)->first();

            // Execute batch update
            $result = $this->levelService->batchUpdateLevels($batchId, $updates, $updaterUser->id);

            return response()->json([
                'success' => true,
                'message' => 'Batch update completed',
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            Log::error('Batch update failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Batch update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user level history
     *
     * GET /api/users/{userId}/level-history?batchId=1
     */
    public function getLevelHistory(Request $request, int $userId): JsonResponse
    {
        try {
            $request->validate([
                'batchId' => 'required|integer|min:1',
            ]);

            $batchId = $request->input('batchId');

            $history = $this->levelService->getUserLevelHistory($userId, $batchId);

            return response()->json([
                'success' => true,
                'message' => 'Level history retrieved successfully',
                'data' => $history->map(function ($record) {
                    return [
                        'userLevelId' => $record->userLevelId,
                        'level' => $record->level,
                        'isActive' => $record->isActive,
                        'createdBy' => $record->creator ? [
                            'username' => $record->creator->username,
                            'userFullName' => $record->creator->userFullName,
                        ] : null,
                        'updatedBy' => $record->updater ? [
                            'username' => $record->updater->username,
                            'userFullName' => $record->updater->userFullName,
                        ] : null,
                        'createdAt' => $record->createdAt->format('Y-m-d H:i:s'),
                        'updatedAt' => $record->updatedAt?->format('Y-m-d H:i:s'),
                    ];
                })
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get level history', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get level history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
