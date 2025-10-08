<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DutyRoster;
use App\Services\UserPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class UserController extends Controller
{
    protected $permissionService;

    public function __construct(UserPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
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
}
