<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcTeamLevelConfig;
use App\Models\TblCcUserLevel;
use App\Models\User;
use App\Services\TeamLevelConfigService;
use App\Services\TeamLevelAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class TeamLevelConfigController extends Controller
{
    protected TeamLevelConfigService $configService;

    public function __construct(TeamLevelConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Get config for target date (approved if exists, otherwise suggested)
     *
     * GET /api/cc/team-level-config/{targetDate}
     *
     * @param string $targetDate
     * @param Request $request
     * @return JsonResponse
     */
    public function getSuggestedConfig(string $targetDate, Request $request): JsonResponse
    {
        try {
            $creatorUser = User::first();
            if (!$creatorUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creator user not found',
                ], 404);
            }

            Log::info('Getting config for target date', [
                'target_date' => $targetDate
            ]);

            // ✅ NEW LOGIC: Regenerate if duty roster changed (batch 1 only)
            $config = $this->configService->regenerateSuggestedConfigIfNeeded($targetDate, $creatorUser->id);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot generate config: No duty roster or unassigned calls found for batch 1.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Config retrieved successfully',
                'data' => $this->formatConfigResponse($config)
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get config', [
                'target_date' => $targetDate,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get config',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save or update configuration
     *
     * POST /api/v1/cc/team-level-config
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function saveConfig(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'targetDate' => 'required|date',
                'teamLeaderPercentage' => 'required|numeric|min:0|max:100',
                'seniorPercentage' => 'required|numeric|min:0|max:100',
                'midLevelPercentage' => 'required|numeric|min:0|max:100',
                'juniorPercentage' => 'required|numeric|min:0|max:100',
                'remarks' => 'nullable|string',
                'createdBy' => 'required|integer|exists:users,authUserId',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check total percentage = 100
            $total = $request->teamLeaderPercentage + $request->seniorPercentage +
                     $request->midLevelPercentage + $request->juniorPercentage;

            if (abs($total - 100) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total percentage must equal 100%',
                    'errors' => ['percentages' => ['Total is ' . $total . '%']]
                ], 422);
            }

            $createdByAuthUserId = $request->input('createdBy');
            $creatorUser = User::where('authUserId', $createdByAuthUserId)->first();

            DB::beginTransaction();

            // Get suggested config to get agent counts
            $suggestedConfig = TblCcTeamLevelConfig::suggested()
                ->active()
                ->where('batchId', 1)  // ← THÊM
                ->forDate($request->targetDate)
                ->first();

            if (!$suggestedConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'No suggested config found for this date',
                ], 404);
            }

            // Deactivate previous approved config for this date
            TblCcTeamLevelConfig::approved()
                ->forDate($request->targetDate)
                ->update(['isActive' => false]);

            // Create new approved config
            $config = TblCcTeamLevelConfig::create([
                'targetDate' => $request->targetDate,
                'batchId' => 1,
                'teamLeaderCount' => $suggestedConfig->teamLeaderCount,
                'seniorCount' => $suggestedConfig->seniorCount,
                'midLevelCount' => $suggestedConfig->midLevelCount,
                'juniorCount' => $suggestedConfig->juniorCount,
                'totalAgents' => $suggestedConfig->totalAgents,
                'totalCalls' => $suggestedConfig->totalCalls,
                'teamLeaderPercentage' => $request->teamLeaderPercentage,
                'seniorPercentage' => $request->seniorPercentage,
                'midLevelPercentage' => $request->midLevelPercentage,
                'juniorPercentage' => $request->juniorPercentage,
                'configType' => TblCcTeamLevelConfig::TYPE_APPROVED,
                'isActive' => true,
                'isAssigned' => false,
                'remarks' => $request->remarks,
                'basedOnConfigId' => $suggestedConfig->configId,
                'createdBy' => $creatorUser->id,
                'approvedBy' => $creatorUser->id,  // ← THÊM: Giống với createdBy
                'approvedAt' => now(),              // ← THÊM: Thời gian approve
            ]);

            DB::commit();

            // ✅ NEW: Lock duty roster for batch 1 when config is saved
            \App\Models\DutyRoster::where('work_date', $request->targetDate)
                ->where('batchId', 1)
                ->update(['isAssigned' => true]);

            Log::info('Config saved and duty roster locked', [
                'config_id' => $config->configId,
                'target_date' => $request->targetDate
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Config saved successfully',
                'data' => $this->formatConfigResponse($config)
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to save config', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save config',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve configuration
     *
     * POST /api/v1/cc/team-level-config/{configId}/approve
     *
     * @param int $configId
     * @param Request $request
     * @return JsonResponse
     */
    public function approveConfig(int $configId, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'approvedBy' => 'required|integer|exists:users,authUserId',
            ]);

            $approvedByAuthUserId = $request->input('approvedBy');
            $approverUser = User::where('authUserId', $approvedByAuthUserId)->first();

            $config = TblCcTeamLevelConfig::find($configId);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Config not found',
                ], 404);
            }

            if ($config->configType !== TblCcTeamLevelConfig::TYPE_SUGGESTED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only suggested configs can be approved',
                ], 400);
            }

            DB::beginTransaction();

            // Deactivate other configs for this date
            TblCcTeamLevelConfig::approved()
                ->forDate($config->targetDate)
                ->update(['isActive' => false]);

            // Update config to approved
            $config->update([
                'configType' => TblCcTeamLevelConfig::TYPE_APPROVED,
                'approvedBy' => $approverUser->id,
                'approvedAt' => now(),
            ]);

            DB::commit();

            // ✅ NEW: Lock duty roster for batch 1 when config is approved
            \App\Models\DutyRoster::where('work_date', $config->targetDate)
                ->where('batchId', 1)
                ->update(['isAssigned' => true]);

            Log::info('Config approved and duty roster locked', [
                'config_id' => $config->configId,
                'target_date' => $config->targetDate->format('Y-m-d')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Config approved successfully',
                'data' => $this->formatConfigResponse($config->fresh())
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to approve config', [
                'config_id' => $configId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve config',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unapprove configuration (revert from approved to suggested)
     *
     * POST /api/cc/team-level-config/{configId}/unapprove
     *
     * @param int $configId
     * @param Request $request
     * @return JsonResponse
     */
    public function unapproveConfig(int $configId, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'updatedBy' => 'required|integer|exists:users,authUserId',
            ]);

            $updatedByAuthUserId = $request->input('updatedBy');
            $updatedByUser = User::where('authUserId', $updatedByAuthUserId)->first();

            $config = TblCcTeamLevelConfig::find($configId);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Config not found',
                ], 404);
            }

            if ($config->configType !== TblCcTeamLevelConfig::TYPE_APPROVED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved configs can be unapproved',
                ], 400);
            }

            // Check if config has been used for assignment
            if ($config->isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot unapprove config that has already been used for assignment',
                ], 400);
            }

            DB::beginTransaction();

            // Update config back to suggested
            $config->update([
                'configType' => TblCcTeamLevelConfig::TYPE_SUGGESTED,
                'approvedBy' => null,      // Clear approval info
                'approvedAt' => null,      // Clear approval timestamp
                'updatedBy' => $updatedByUser->id,
                'updatedAt' => now(),
            ]);

            DB::commit();

            Log::info('Config unapproved successfully', [
                'config_id' => $config->configId,
                'updated_by_auth_user_id' => $updatedByAuthUserId,
                'target_date' => $config->targetDate->format('Y-m-d')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Config unapproved successfully',
                'data' => $this->formatConfigResponse($config->fresh())
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to unapprove config', [
                'config_id' => $configId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unapprove config',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configuration history
     *
     * GET /api/v1/cc/team-level-config/history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fromDate' => 'nullable|date',
                'toDate' => 'nullable|date|after_or_equal:fromDate',
                'configType' => 'nullable|string|in:suggested,approved',
                'limit' => 'nullable|integer|min:1|max:100',
                'batchId' => 'nullable|integer',  // ← THÊM validation
            ]);

            $query = TblCcTeamLevelConfig::with(['creator', 'updater', 'approver', 'assigner', 'basedOnConfig'])  // ← THÊM 'assigner'
                ->orderBy('targetDate', 'desc');

            // Apply filters
            if ($request->fromDate) {
                $query->where('targetDate', '>=', $request->fromDate);
            }

            if ($request->toDate) {
                $query->where('targetDate', '<=', $request->toDate);
            }

            if ($request->configType) {
                $query->where('configType', $request->configType);
            }

            if ($request->batchId) {  // ← THÊM filter by batchId
                $query->where('batchId', $request->batchId);
            }

            $limit = $request->input('limit', 30);
            $configs = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'message' => 'History retrieved successfully',
                'data' => $configs->map(fn($config) => $this->formatConfigResponse($config)),
                'total' => $configs->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get history', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format config response
     *
     * @param TblCcTeamLevelConfig $config
     * @return array
     */
    private function formatConfigResponse(TblCcTeamLevelConfig $config): array
    {
        // Get users by level for this batch
        $usersByLevel = $this->getUsersByLevelForBatch(
            $config->batchId,
            $config->targetDate->toDateString()
        );

        return [
            'configId' => $config->configId,
            'targetDate' => $config->targetDate->format('Y-m-d'),
            'batchId' => $config->batchId,
            'agentCounts' => [
                'teamLeader' => $config->teamLeaderCount,
                'senior' => $config->seniorCount,
                'midLevel' => $config->midLevelCount,
                'junior' => $config->juniorCount,
                'total' => $config->totalAgents,
            ],
            'totalCalls' => $config->totalCalls,
            'percentages' => [
                'teamLeader' => (float) $config->teamLeaderPercentage,
                'senior' => (float) $config->seniorPercentage,
                'midLevel' => (float) $config->midLevelPercentage,
                'junior' => (float) $config->juniorPercentage,
            ],
            'agentsByLevel' => $usersByLevel,
            'assignmentsByUser' => $config->assignmentsByUser,
            'configType' => $config->configType,
            'isActive' => $config->isActive,
            'isAssigned' => $config->isAssigned,
            'remarks' => $config->remarks,
            'basedOnConfigId' => $config->basedOnConfigId,
            'createdBy' => $config->creator ? [
                'id' => $config->creator->id,
                'authUserId' => $config->creator->authUserId,
                'username' => $config->creator->username,
                'userFullName' => $config->creator->userFullName,
            ] : null,
            'approvedBy' => $config->approver ? [
                'id' => $config->approver->id,
                'authUserId' => $config->approver->authUserId,
                'username' => $config->approver->username,
                'userFullName' => $config->approver->userFullName,
            ] : null,
            'assignedBy' => $config->assigner ? [           // ← THÊM: assignedBy info
                'id' => $config->assigner->id,
                'authUserId' => $config->assigner->authUserId,
                'username' => $config->assigner->username,
                'userFullName' => $config->assigner->userFullName,
            ] : null,
            'createdAt' => $config->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updatedAt' => $config->updatedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
            'approvedAt' => $config->approvedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
            'assignedAt' => $config->assignedAt?->utc()->format('Y-m-d\TH:i:s\Z'),  // ← THÊM
        ];
    }

    /**
     * Get users grouped by level for a specific batch
     * ONLY returns users who are in duty roster for the target date
     *
     * @param int $batchId
     * @param string $targetDate
     * @return array
     */
    private function getUsersByLevelForBatch(int $batchId, string $targetDate): array
    {
        // ✅ Step 1: Get users in duty roster for this date and batch
        $dutyRosterUsers = \App\Models\DutyRoster::where('work_date', $targetDate)
            ->where('batchId', $batchId)
            ->where('is_working', true)
            ->pluck('user_id')
            ->toArray();

        if (empty($dutyRosterUsers)) {
            return [
                'teamLeader' => [],
                'senior' => [],
                'midLevel' => [],
                'junior' => [],
            ];
        }

        Log::info('Users in duty roster', [
            'target_date' => $targetDate,
            'batch_id' => $batchId,
            'user_ids' => $dutyRosterUsers,
            'count' => count($dutyRosterUsers)
        ]);

        // ✅ Step 2: Get user levels ONLY for users in duty roster
        $userLevels = \App\Models\TblCcUserLevel::with('user')
            ->where('batchId', $batchId)
            ->where('isActive', true)
            ->whereIn('userId', $dutyRosterUsers)  // ✅ CHỈ lấy users trong duty roster
            ->get();

        // Group by level
        $grouped = [
            'teamLeader' => [],
            'senior' => [],
            'midLevel' => [],
            'junior' => [],
        ];

        foreach ($userLevels as $userLevel) {
            $user = $userLevel->user;

            if (!$user) {
                continue;
            }

            $userData = [
                'id' => $user->id,
                'authUserId' => $user->authUserId,
                'username' => $user->username,
                'userFullName' => $user->userFullName,
                'email' => $user->email,
            ];

            // Map level to camelCase keys
            switch ($userLevel->level) {
                case 'team-leader':
                    $grouped['teamLeader'][] = $userData;
                    break;
                case 'senior':
                    $grouped['senior'][] = $userData;
                    break;
                case 'mid-level':
                    $grouped['midLevel'][] = $userData;
                    break;
                case 'junior':
                    $grouped['junior'][] = $userData;
                    break;
            }
        }

        return $grouped;
    }

    /**
     * Assign calls based on team level percentage
     *
     * POST /api/cc/team-level-config/assign-calls
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignCalls(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'targetDate' => 'required|date',
                'configId' => 'required|integer|exists:tbl_CcTeamLevelConfig,configId',
                'assignedBy' => 'required|integer|exists:users,authUserId',
            ]);

            $targetDate = $request->input('targetDate');
            $configId = $request->input('configId');
            $assignedByAuthUserId = $request->input('assignedBy');

            // ✅ THÊM: Get config và check
            $config = TblCcTeamLevelConfig::find($configId);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Config not found',
                ], 404);
            }

            if ($config->isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'This config has already been used for assignment',
                ], 400);
            }

            Log::info('Assign calls request received', [
                'target_date' => $targetDate,
                'config_id' => $configId,
                'assigned_by' => $assignedByAuthUserId
            ]);

            // Execute assignment
            $assignmentService = app(TeamLevelAssignmentService::class);
            $result = $assignmentService->assignCallsByTeamLevel(
                $targetDate,
                $configId,
                $assignedByAuthUserId
            );

            // ✅ THÊM: Update isAssigned = true
            $config->update([
                'isAssigned' => true,
                'assignedBy' => $creatorUser->id,  // ← THÊM: Lưu local user id
                'assignedAt' => now(),              // ← THÊM: Thời gian assign
            ]);

            // ✅ NEW: Also lock duty roster for batch 1
            \App\Models\DutyRoster::where('work_date', $targetDate)
                ->where('batchId', 1)
                ->update(['isAssigned' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Calls assigned successfully',
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to assign calls', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign calls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview call assignments based on team level percentage
     * Does NOT actually assign - just shows what would happen
     *
     * POST /api/cc/team-level-config/preview-assignment
     *
     * Body: {
     *   "targetDate": "2025-11-04",
     *   "configId": 5
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function previewAssignment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'targetDate' => 'required|date',
                'configId' => 'required|integer|exists:tbl_CcTeamLevelConfig,configId',
            ]);

            $targetDate = $request->input('targetDate');
            $configId = $request->input('configId');

            Log::info('Preview assignment request received', [
                'target_date' => $targetDate,
                'config_id' => $configId
            ]);

            // Execute preview (no database changes)
            $assignmentService = app(TeamLevelAssignmentService::class);
            $result = $assignmentService->previewAssignment($targetDate, $configId);

            return response()->json([
                'success' => true,
                'message' => 'Assignment preview generated successfully',
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to preview assignment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to preview assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
