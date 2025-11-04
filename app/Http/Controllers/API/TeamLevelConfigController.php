<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcTeamLevelConfig;
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
            $request->validate([
                'createdBy' => 'required|integer|exists:users,authUserId',
            ]);

            $createdByAuthUserId = $request->input('createdBy');

            // Get local user id
            $creatorUser = User::where('authUserId', $createdByAuthUserId)->first();
            if (!$creatorUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creator user not found',
                ], 404);
            }

            Log::info('Getting config for target date', [
                'target_date' => $targetDate
            ]);

            // ✅ THAY ĐÔI LOGIC: Priority approved > suggested
            // First try to get approved config
            $config = TblCcTeamLevelConfig::approved()
                ->active()
                ->forDate($targetDate)
                ->first();

            // If no approved config, get or create suggested config
            if (!$config) {
                $config = $this->configService->getSuggestedConfig($targetDate, $creatorUser->id);
            }

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
                'batchId' => 1,  // ← THÊM
                'teamLeaderCount' => $suggestedConfig->teamLeaderCount,
                'seniorCount' => $suggestedConfig->seniorCount,
                'midLevelCount' => $suggestedConfig->midLevelCount,
                'juniorCount' => $suggestedConfig->juniorCount,
                'totalAgents' => $suggestedConfig->totalAgents,
                'totalCalls' => $suggestedConfig->totalCalls,  // ← THÊM
                'teamLeaderPercentage' => $request->teamLeaderPercentage,
                'seniorPercentage' => $request->seniorPercentage,
                'midLevelPercentage' => $request->midLevelPercentage,
                'juniorPercentage' => $request->juniorPercentage,
                'configType' => TblCcTeamLevelConfig::TYPE_APPROVED,
                'isActive' => true,
                'remarks' => $request->remarks,
                'basedOnConfigId' => $suggestedConfig->configId,
                'createdBy' => $creatorUser->id,
            ]);

            DB::commit();

            Log::info('Config saved', [
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

            Log::info('Config approved', [
                'config_id' => $configId,
                'approved_by' => $approverUser->id
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
            ]);

            $query = TblCcTeamLevelConfig::with(['creator', 'updater', 'approver', 'basedOnConfig'])
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
            'configType' => $config->configType,
            'isActive' => $config->isActive,
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
            'createdAt' => $config->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $config->updatedAt?->format('Y-m-d H:i:s'),
            'approvedAt' => $config->approvedAt?->format('Y-m-d H:i:s'),
        ];
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
}
