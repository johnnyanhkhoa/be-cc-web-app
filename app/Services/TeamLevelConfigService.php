<?php

namespace App\Services;

use App\Models\TblCcTeamLevelConfig;
use App\Models\DutyRoster;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class TeamLevelConfigService
{
    /**
     * Base ratio for percentage calculation
     * team-leader : senior : mid-level : junior = 24:37:22:17
     */
    const BASE_RATIO = [
        'team-leader' => 24,
        'senior' => 37,
        'mid-level' => 22,
        'junior' => 17,
    ];

    /**
     * Generate suggested config based on duty roster and unassigned calls for batchId 1
     *
     * @param string $targetDate
     * @param int $createdBy Local user id
     * @return TblCcTeamLevelConfig|null
     */
    public function generateSuggestedConfig(string $targetDate, int $createdBy): ?TblCcTeamLevelConfig
    {
        try {
            Log::info('Generating suggested config', [
                'target_date' => $targetDate,
                'created_by' => $createdBy
            ]);

            // ✅ CHECK 1: Get duty roster for batchId = 1 (past-due)
            $dutyRosters = DutyRoster::with('user')
                ->where('work_date', $targetDate)
                ->where('batchId', 1)
                ->where('is_working', true)
                ->get();

            if ($dutyRosters->isEmpty()) {
                Log::warning('No duty roster found for batchId 1', [
                    'target_date' => $targetDate
                ]);
                return null;
            }

            // ✅ CHECK 2: Get unassigned calls for batchId = 1
            $unassignedCalls = \App\Models\TblCcPhoneCollection::where('batchId', 1)
                ->whereNull('assignedTo')
                ->count();

            if ($unassignedCalls === 0) {
                Log::warning('No unassigned calls found for batchId 1', [
                    'target_date' => $targetDate
                ]);
                return null;
            }

            Log::info('Found unassigned calls', [
                'target_date' => $targetDate,
                'unassigned_calls' => $unassignedCalls
            ]);

            // ✅ CHECK 3: Check if config already exists (avoid duplicates)
            $existingConfig = TblCcTeamLevelConfig::where('targetDate', $targetDate)
                ->where('batchId', 1)
                ->where('configType', TblCcTeamLevelConfig::TYPE_SUGGESTED)
                ->where('isActive', true)
                ->first();

            if ($existingConfig) {
                Log::info('Suggested config already exists, returning existing', [
                    'config_id' => $existingConfig->configId,
                    'target_date' => $targetDate
                ]);
                return $existingConfig;
            }

            // Count agents by level
            $counts = [
                'team-leader' => 0,
                'senior' => 0,
                'mid-level' => 0,
                'junior' => 0,
            ];

            foreach ($dutyRosters as $roster) {
                $level = $roster->user->level ?? null;
                if ($level && isset($counts[$level])) {
                    $counts[$level]++;
                }
            }

            $totalAgents = array_sum($counts);

            if ($totalAgents === 0) {
                Log::warning('No agents with valid levels found', [
                    'target_date' => $targetDate
                ]);
                return null;
            }

            // Calculate suggested percentages
            $percentages = $this->calculateSuggestedPercentages($counts, $totalAgents);

            // Get previous approved config to use as base
            $previousConfig = TblCcTeamLevelConfig::approved()
                ->active()
                ->where('batchId', 1)
                ->where('targetDate', '<', $targetDate)
                ->orderBy('targetDate', 'desc')
                ->first();

            // Deactivate any existing suggested config for this date and batchId
            TblCcTeamLevelConfig::suggested()
                ->where('targetDate', $targetDate)
                ->where('batchId', 1)
                ->update(['isActive' => false]);

            // Create new suggested config
            $config = TblCcTeamLevelConfig::create([
                'targetDate' => $targetDate,
                'batchId' => 1,  // ← THÊM
                'teamLeaderCount' => $counts['team-leader'],
                'seniorCount' => $counts['senior'],
                'midLevelCount' => $counts['mid-level'],
                'juniorCount' => $counts['junior'],
                'totalAgents' => $totalAgents,
                'totalCalls' => $unassignedCalls,  // ← THÊM
                'teamLeaderPercentage' => $percentages['team-leader'],
                'seniorPercentage' => $percentages['senior'],
                'midLevelPercentage' => $percentages['mid-level'],
                'juniorPercentage' => $percentages['junior'],
                'configType' => TblCcTeamLevelConfig::TYPE_SUGGESTED,
                'isActive' => true,
                'isAssigned' => false,
                'basedOnConfigId' => $previousConfig?->configId,
                'createdBy' => $createdBy,
            ]);

            Log::info('Suggested config created', [
                'config_id' => $config->configId,
                'target_date' => $targetDate,
                'total_calls' => $unassignedCalls,
                'percentages' => $percentages
            ]);

            return $config;

        } catch (Exception $e) {
            Log::error('Failed to generate suggested config', [
                'target_date' => $targetDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate suggested percentages based on agent counts
     *
     * @param array $counts
     * @param int $totalAgents
     * @return array
     */
    private function calculateSuggestedPercentages(array $counts, int $totalAgents): array
    {
        $percentages = [];
        $totalPercentage = 0;

        // Calculate weighted percentages
        foreach ($counts as $level => $count) {
            if ($count > 0) {
                // Weight = (count / total) * baseRatio
                $weight = ($count / $totalAgents) * self::BASE_RATIO[$level];
                $percentages[$level] = $weight;
                $totalPercentage += $weight;
            } else {
                $percentages[$level] = 0;
            }
        }

        // Normalize to 100%
        if ($totalPercentage > 0) {
            foreach ($percentages as $level => $percentage) {
                $percentages[$level] = round(($percentage / $totalPercentage) * 100, 2);
            }
        }

        // Ensure total is exactly 100% by adjusting the largest percentage
        $sum = array_sum($percentages);
        if ($sum != 100) {
            $diff = 100 - $sum;
            $maxLevel = array_search(max($percentages), $percentages);
            $percentages[$maxLevel] = round($percentages[$maxLevel] + $diff, 2);
        }

        return $percentages;
    }

    /**
     * Get or create suggested config for target date
     *
     * @param string $targetDate
     * @param int $createdBy
     * @return TblCcTeamLevelConfig|null
     */
    public function getSuggestedConfig(string $targetDate, int $createdBy): ?TblCcTeamLevelConfig
    {
        // Check if suggested config already exists
        $config = TblCcTeamLevelConfig::suggested()
            ->active()
            ->where('batchId', 1)
            ->forDate($targetDate)
            ->first();

        if ($config) {
            Log::info('Using existing suggested config', [
                'config_id' => $config->configId
            ]);
            return $config;
        }

        // Generate new suggested config (only if duty roster + calls exist)
        return $this->generateSuggestedConfig($targetDate, $createdBy);
    }

    /**
     * Get approved config for target date (or use previous day's config)
     *
     * @param string $targetDate
     * @return TblCcTeamLevelConfig|null
     */
    public function getApprovedConfig(string $targetDate): ?TblCcTeamLevelConfig
    {
        // Try to get approved config for this date
        $config = TblCcTeamLevelConfig::approved()
            ->active()
            ->where('batchId', 1)  // ← THÊM
            ->forDate($targetDate)
            ->first();

        if ($config) {
            return $config;
        }

        // If not found, get the most recent approved config
        return TblCcTeamLevelConfig::approved()
            ->active()
            ->where('batchId', 1)  // ← THÊM
            ->where('targetDate', '<', $targetDate)
            ->orderBy('targetDate', 'desc')
            ->first();
    }
}
