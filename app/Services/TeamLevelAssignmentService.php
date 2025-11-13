<?php

namespace App\Services;

use App\Models\TblCcTeamLevelConfig;
use App\Models\TblCcPhoneCollection;
use App\Models\DutyRoster;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TeamLevelAssignmentService
{
    /**
     * Assign calls based on team level percentage config
     *
     * @param string $targetDate
     * @param int $configId
     * @param int $assignedByAuthUserId
     * @return array
     * @throws Exception
     */
    public function assignCallsByTeamLevel(string $targetDate, int $configId, int $assignedByAuthUserId): array
    {
        try {
            Log::info('Starting team level assignment', [
                'target_date' => $targetDate,
                'config_id' => $configId,
                'assigned_by' => $assignedByAuthUserId
            ]);

            // 1. Get config
            $config = TblCcTeamLevelConfig::find($configId);

            if (!$config) {
                throw new Exception("Config with ID {$configId} not found");
            }

            if ($config->configType !== TblCcTeamLevelConfig::TYPE_APPROVED) {
                throw new Exception("Only approved configs can be used for assignment");
            }

            // 2. Get duty roster agents grouped by level
            $agentsByLevel = $this->getAgentsByLevel($targetDate);

            if (empty(array_filter($agentsByLevel))) {
                throw new Exception("No agents found in duty roster for this date");
            }

            // 3. Get unassigned calls for batchId = 1, grouped by DPD
            $callsByDpd = $this->getUnassignedCallsByDpd();

            if ($callsByDpd->isEmpty()) {
                throw new Exception("No unassigned calls found for batch 1");
            }

            $totalCalls = $callsByDpd->flatten()->count();

            Log::info('Assignment data loaded', [
                'total_calls' => $totalCalls,
                'dpd_groups' => $callsByDpd->keys()->toArray(),
                'agents_by_level' => array_map('count', $agentsByLevel)
            ]);

            // 4. Execute assignment
            $assignments = $this->executeAssignment(
                $config,
                $agentsByLevel,
                $callsByDpd,
                $assignedByAuthUserId
            );

            Log::info('Team level assignment completed', [
                'total_assigned' => count($assignments),
                'config_id' => $configId
            ]);

            return [
                'success' => true,
                'total_assigned' => count($assignments),
                'assignments_by_level' => $this->summarizeByLevel($assignments),
                'assignments_by_user' => $this->summarizeByUser($assignments),
            ];

            // ✅ Save assignments to config
            $assignmentsByUser = $this->summarizeByUser($assignments);

            $config = TblCcTeamLevelConfig::find($configId);
            if ($config) {
                $config->assignmentsByUser = $assignmentsByUser;
                $config->save();

                Log::info('Assignments saved to config', [
                    'config_id' => $configId,
                    'users_count' => count($assignmentsByUser)
                ]);
            }

            return [
                'success' => true,
                'total_assigned' => count($assignments),
                'assignments_by_level' => $this->summarizeByLevel($assignments),
                'assignments_by_user' => $assignmentsByUser,
            ];

        } catch (Exception $e) {
            Log::error('Team level assignment failed', [
                'target_date' => $targetDate,
                'config_id' => $configId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get agents from duty roster grouped by level
     *
     * @param string $targetDate
     * @return array
     */
    private function getAgentsByLevel(string $targetDate): array
    {
        $dutyRosters = DutyRoster::with('user')
            ->where('work_date', $targetDate)
            ->where('batchId', 1)
            ->where('is_working', true)
            ->get();

        $agentsByLevel = [
            'team-leader' => [],
            'senior' => [],
            'mid-level' => [],
            'junior' => [],
        ];

        foreach ($dutyRosters as $roster) {
            // ✅ Get level from tbl_CcUserLevel for batchId 1
            $level = \App\Models\TblCcUserLevel::getActiveLevel($roster->user->id, 1);

            if ($level && isset($agentsByLevel[$level])) {
                $agentsByLevel[$level][] = $roster->user;
            }
        }

        return $agentsByLevel;
    }

    /**
     * Get unassigned calls for batch 1, grouped by DPD
     *
     * @return \Illuminate\Support\Collection
     */
    private function getUnassignedCallsByDpd()
    {
        $calls = TblCcPhoneCollection::where('batchId', 1)
            ->whereNull('assignedTo')
            ->orderBy('daysOverdueGross', 'asc')
            ->orderBy('phoneCollectionId', 'asc')
            ->get();

        return $calls->groupBy('daysOverdueGross');
    }

    /**
     * Execute the assignment logic with balanced DPD distribution
     *
     * @param TblCcTeamLevelConfig $config
     * @param array $agentsByLevel
     * @param \Illuminate\Support\Collection $callsByDpd
     * @param int $assignedByAuthUserId
     * @return array
     */
    private function executeAssignment($config, $agentsByLevel, $callsByDpd, $assignedByAuthUserId): array
    {
        $assignments = [];
        $percentages = [
            'team-leader' => $config->teamLeaderPercentage,
            'senior' => $config->seniorPercentage,
            'mid-level' => $config->midLevelPercentage,
            'junior' => $config->juniorPercentage,
        ];

        DB::beginTransaction();

        try {
            // Step 1: Calculate TOTAL allocation for each level
            $allCalls = $callsByDpd->flatten();
            $totalCalls = $allCalls->count();

            $totalAllocations = $this->calculateAllocations($totalCalls, $percentages);

            // Track remaining quota for each level
            $remainingQuota = $totalAllocations;

            Log::info('Total allocations calculated', [
                'total_calls' => $totalCalls,
                'allocations' => $totalAllocations
            ]);

            // ✅ NEW LOGIC: Allocate calls from each DPD group proportionally
            // Step 2: For each DPD group, calculate how many calls each level should get
            $levelDpdAllocations = []; // [level][dpd] = count

            foreach ($callsByDpd as $dpd => $dpdCalls) {
                $dpdCount = $dpdCalls->count();

                // Calculate allocation for this DPD group
                $dpdAllocations = $this->calculateAllocations($dpdCount, $percentages);

                // But limit by remaining quota
                foreach ($dpdAllocations as $level => $count) {
                    $actualCount = min($count, $remainingQuota[$level]);

                    if (!isset($levelDpdAllocations[$level])) {
                        $levelDpdAllocations[$level] = [];
                    }

                    $levelDpdAllocations[$level][$dpd] = $actualCount;
                }
            }

            // Adjust allocations to match total quota exactly
            $levelDpdAllocations = $this->adjustAllocationsToQuota($levelDpdAllocations, $remainingQuota, $callsByDpd);

            Log::info('DPD allocations calculated', [
                'allocations' => $levelDpdAllocations
            ]);

            // ✅ NEW: Track user index for each level to ensure fair distribution
            $userIndexByLevel = [
                'team-leader' => 0,
                'senior' => 0,
                'mid-level' => 0,
                'junior' => 0,
            ];

            // Step 3: Assign calls based on calculated allocations
            foreach ($callsByDpd as $dpd => $dpdCalls) {
                Log::info('Processing DPD group', [
                    'dpd' => $dpd,
                    'total_calls' => $dpdCalls->count()
                ]);

                foreach (['team-leader', 'senior', 'mid-level', 'junior'] as $level) {
                    $count = $levelDpdAllocations[$level][$dpd] ?? 0;

                    if ($count <= 0) {
                        continue;
                    }

                    $users = $agentsByLevel[$level] ?? [];

                    if (empty($users)) {
                        Log::warning('No agents for level, skipping', ['level' => $level]);
                        continue;
                    }

                    // Take calls from this DPD group
                    $callsForLevel = $dpdCalls->splice(0, $count);

                    // ✅ FIX: Use persistent userIndex across DPD groups
                    $userIndex = $userIndexByLevel[$level];

                    foreach ($callsForLevel as $call) {
                        $user = $users[$userIndex];

                        // Update call
                        $call->update([
                            'assignedTo' => $user->authUserId,
                            'assignedBy' => $assignedByAuthUserId,
                            'assignedAt' => now(),
                            'status' => 'pending',
                            'updatedBy' => $assignedByAuthUserId,
                        ]);

                        $assignments[] = [
                            'phoneCollectionId' => $call->phoneCollectionId,
                            'contractNo' => $call->contractNo,
                            'dpd' => $dpd,
                            'level' => $level,
                            'user_id' => $user->id,
                            'user_auth_user_id' => $user->authUserId,
                            'user_name' => $user->userFullName,
                        ];

                        // Move to next user (round-robin)
                        $userIndex = ($userIndex + 1) % count($users);

                        // Decrease quota
                        $remainingQuota[$level]--;
                    }

                    // ✅ IMPORTANT: Save the userIndex for next DPD group
                    $userIndexByLevel[$level] = $userIndex;

                    Log::info('Assigned calls for level', [
                        'level' => $level,
                        'dpd' => $dpd,
                        'assigned' => $count,
                        'remaining_quota' => $remainingQuota[$level]
                    ]);
                }
            }

            // ✅ NEW: Step 4: Handle leftover calls (if any)
            $leftoverCalls = collect();
            foreach ($callsByDpd as $dpd => $dpdCalls) {
                if ($dpdCalls->isNotEmpty()) {
                    $leftoverCalls = $leftoverCalls->merge($dpdCalls);
                }
            }

            if ($leftoverCalls->isNotEmpty()) {
                Log::info('Found leftover calls after main assignment', [
                    'count' => $leftoverCalls->count()
                ]);

                $leftoverAssignments = $this->assignLeftoverCalls(
                    $leftoverCalls,
                    $agentsByLevel,
                    $assignedByAuthUserId
                );

                $assignments = array_merge($assignments, $leftoverAssignments);
            }

            DB::commit();

            return $assignments;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Simulate assignment without saving to database
     *
     * @param TblCcTeamLevelConfig $config
     * @param array $agentsByLevel
     * @param \Illuminate\Support\Collection $callsByDpd
     * @return array
     */
    private function simulateAssignment($config, $agentsByLevel, $callsByDpd): array
    {
        $assignments = [];
        $percentages = [
            'team-leader' => $config->teamLeaderPercentage,
            'senior' => $config->seniorPercentage,
            'mid-level' => $config->midLevelPercentage,
            'junior' => $config->juniorPercentage,
        ];

        // Calculate TOTAL allocation for each level
        $allCalls = $callsByDpd->flatten();
        $totalCalls = $allCalls->count();

        $totalAllocations = $this->calculateAllocations($totalCalls, $percentages);

        // Track remaining quota for each level
        $remainingQuota = $totalAllocations;

        // ✅ NEW: Track user index for each level to ensure fair distribution
        $userIndexByLevel = [
            'team-leader' => 0,
            'senior' => 0,
            'mid-level' => 0,
            'junior' => 0,
        ];

        // Calculate allocations for each DPD group
        $levelDpdAllocations = [];

        foreach ($callsByDpd as $dpd => $dpdCalls) {
            $dpdCount = $dpdCalls->count();

            $dpdAllocations = $this->calculateAllocations($dpdCount, $percentages);

            foreach ($dpdAllocations as $level => $count) {
                $actualCount = min($count, $remainingQuota[$level]);

                if (!isset($levelDpdAllocations[$level])) {
                    $levelDpdAllocations[$level] = [];
                }

                $levelDpdAllocations[$level][$dpd] = $actualCount;
            }
        }

        $levelDpdAllocations = $this->adjustAllocationsToQuota($levelDpdAllocations, $remainingQuota, $callsByDpd);

        // ✅ Create a copy of callsByDpd to avoid modifying original
        $callsByDpdCopy = collect();
        foreach ($callsByDpd as $dpd => $dpdCalls) {
            $callsByDpdCopy[$dpd] = collect($dpdCalls->all());
        }

        // Simulate assignments (WITHOUT saving to database)
        foreach ($callsByDpdCopy as $dpd => $dpdCalls) {
            foreach (['team-leader', 'senior', 'mid-level', 'junior'] as $level) {
                $count = $levelDpdAllocations[$level][$dpd] ?? 0;

                if ($count <= 0) {
                    continue;
                }

                $users = $agentsByLevel[$level] ?? [];

                if (empty($users)) {
                    continue;
                }

                // ✅ FIX: Take and remove calls properly
                $callsForLevel = $dpdCalls->splice(0, $count);

                // ✅ FIX: Use persistent userIndex across DPD groups
                $userIndex = $userIndexByLevel[$level];

                foreach ($callsForLevel as $call) {
                    $user = $users[$userIndex];

                    $assignments[] = [
                        'phoneCollectionId' => $call->phoneCollectionId,
                        'contractNo' => $call->contractNo,
                        'dpd' => $dpd,
                        'level' => $level,
                        'user_id' => $user->id,
                        'user_auth_user_id' => $user->authUserId,
                        'user_name' => $user->userFullName,
                    ];

                    $userIndex = ($userIndex + 1) % count($users);
                    $remainingQuota[$level]--;
                }

                // ✅ IMPORTANT: Save the userIndex for next DPD group
                $userIndexByLevel[$level] = $userIndex;
            }
        }

        // Handle leftover calls
        $leftoverCalls = collect();
        foreach ($callsByDpdCopy as $dpd => $dpdCalls) {
            if ($dpdCalls->isNotEmpty()) {
                $leftoverCalls = $leftoverCalls->merge($dpdCalls);
            }
        }

        if ($leftoverCalls->isNotEmpty()) {
            $allUsers = collect($agentsByLevel)->flatten(1)->values()->all();

            if (!empty($allUsers)) {
                $userIndex = 0;
                foreach ($leftoverCalls as $call) {
                    $user = $allUsers[$userIndex];

                    // ✅ FIX: Get level from TblCcUserLevel
                    $userLevel = \App\Models\TblCcUserLevel::getActiveLevel($user->id, 1);

                    $assignments[] = [
                        'phoneCollectionId' => $call->phoneCollectionId,
                        'contractNo' => $call->contractNo,
                        'dpd' => $call->daysOverdueGross,
                        'level' => $userLevel ?? 'unknown',
                        'user_id' => $user->id,
                        'user_auth_user_id' => $user->authUserId,
                        'user_name' => $user->userFullName,
                    ];

                    $userIndex = ($userIndex + 1) % count($allUsers);
                }
            }
        }

        return [
            'total_assigned' => count($assignments),
            'assignments_by_level' => $this->summarizeByLevel($assignments),
            'assignments_by_user' => $this->summarizeByUser($assignments),
        ];
    }

    /**
     * Adjust allocations to match quota exactly
     *
     * @param array $levelDpdAllocations
     * @param array $targetQuota
     * @param \Illuminate\Support\Collection $callsByDpd
     * @return array
     */
    private function adjustAllocationsToQuota(array $levelDpdAllocations, array $targetQuota, $callsByDpd): array
    {
        // Calculate current total for each level
        $currentTotals = [];
        foreach ($levelDpdAllocations as $level => $dpdCounts) {
            $currentTotals[$level] = array_sum($dpdCounts);
        }

        // Adjust if needed
        foreach ($currentTotals as $level => $currentTotal) {
            $diff = $targetQuota[$level] - $currentTotal;

            if ($diff == 0) {
                continue;
            }

            // Need to add or remove calls
            if ($diff > 0) {
                // Need to add calls - find DPD groups with available calls
                foreach ($callsByDpd as $dpd => $dpdCalls) {
                    if ($diff <= 0) break;

                    $currentAllocation = $levelDpdAllocations[$level][$dpd] ?? 0;
                    $available = $dpdCalls->count();

                    // Calculate how much is already allocated to other levels for this DPD
                    $allocatedToOthers = 0;
                    foreach ($levelDpdAllocations as $otherLevel => $otherDpdCounts) {
                        if ($otherLevel !== $level) {
                            $allocatedToOthers += $otherDpdCounts[$dpd] ?? 0;
                        }
                    }

                    $canTake = $available - $currentAllocation - $allocatedToOthers;
                    $toAdd = min($diff, $canTake);

                    if ($toAdd > 0) {
                        $levelDpdAllocations[$level][$dpd] = $currentAllocation + $toAdd;
                        $diff -= $toAdd;
                    }
                }
            } else {
                // Need to remove calls - remove from largest DPD groups first
                $diff = abs($diff);
                $dpdSorted = collect($levelDpdAllocations[$level])
                    ->sortDesc()
                    ->keys()
                    ->toArray();

                foreach ($dpdSorted as $dpd) {
                    if ($diff <= 0) break;

                    $currentAllocation = $levelDpdAllocations[$level][$dpd];
                    $toRemove = min($diff, $currentAllocation);

                    $levelDpdAllocations[$level][$dpd] -= $toRemove;
                    $diff -= $toRemove;
                }
            }
        }

        return $levelDpdAllocations;
    }

    /**
     * Calculate how many calls each level should get
     *
     * @param int $totalCalls
     * @param array $percentages
     * @return array
     */
    private function calculateAllocations(int $totalCalls, array $percentages): array
    {
        $allocations = [];

        foreach ($percentages as $level => $percentage) {
            $allocations[$level] = (int) round($totalCalls * ($percentage / 100));
        }

        // Adjust for rounding errors
        $totalAllocated = array_sum($allocations);
        $diff = $totalCalls - $totalAllocated;

        if ($diff != 0) {
            // Add/subtract from random level
            $levels = array_keys($allocations);
            $randomLevel = $levels[array_rand($levels)];
            $allocations[$randomLevel] += $diff;
        }

        return $allocations;
    }

    /**
     * Summarize assignments by level
     *
     * @param array $assignments
     * @return array
     */
    private function summarizeByLevel(array $assignments): array
    {
        $summary = [
            'team-leader' => 0,
            'senior' => 0,
            'mid-level' => 0,
            'junior' => 0,
        ];

        foreach ($assignments as $assignment) {
            $level = $assignment['level'];
            if (isset($summary[$level])) {
                $summary[$level]++;
            }
        }

        return $summary;
    }

    /**
     * Summarize assignments by user
     *
     * @param array $assignments
     * @return array
     */
    private function summarizeByUser(array $assignments): array
    {
        $summary = [];

        foreach ($assignments as $assignment) {
            $userId = $assignment['user_id'];

            if (!isset($summary[$userId])) {
                $summary[$userId] = [
                    'user_id' => $userId,
                    'user_auth_user_id' => $assignment['user_auth_user_id'],
                    'user_name' => $assignment['user_name'],
                    'level' => $assignment['level'],
                    'total_calls' => 0,
                    'dpd_distribution' => [],
                ];
            }

            $summary[$userId]['total_calls']++;

            $dpd = $assignment['dpd'];
            if (!isset($summary[$userId]['dpd_distribution'][$dpd])) {
                $summary[$userId]['dpd_distribution'][$dpd] = 0;
            }
            $summary[$userId]['dpd_distribution'][$dpd]++;
        }

        return array_values($summary);
    }

    /**
     * Assign leftover calls using round-robin
     *
     * @param \Illuminate\Support\Collection $leftoverCalls
     * @param array $agentsByLevel
     * @param int $assignedByAuthUserId
     * @return array
     */
    private function assignLeftoverCalls($leftoverCalls, $agentsByLevel, $assignedByAuthUserId): array
    {
        if ($leftoverCalls->isEmpty()) {
            return [];
        }

        Log::info('Assigning leftover calls', [
            'count' => $leftoverCalls->count()
        ]);

        $assignments = [];

        // Flatten all users from all levels into single array
        $allUsers = collect($agentsByLevel)->flatten(1)->values()->all();

        if (empty($allUsers)) {
            Log::warning('No users available for leftover calls');
            return [];
        }

        // Round-robin assign
        $userIndex = 0;
        foreach ($leftoverCalls as $call) {
            $user = $allUsers[$userIndex];

            $call->update([
                'assignedTo' => $user->authUserId,
                'assignedBy' => $assignedByAuthUserId,
                'assignedAt' => now(),
                'status' => 'pending',
                'updatedBy' => $assignedByAuthUserId,
            ]);

            $assignments[] = [
                'phoneCollectionId' => $call->phoneCollectionId,
                'contractNo' => $call->contractNo,
                'dpd' => $call->daysOverdueGross,
                'level' => $user->level,
                'user_id' => $user->id,
                'user_auth_user_id' => $user->authUserId,
                'user_name' => $user->userFullName,
            ];

            $userIndex = ($userIndex + 1) % count($allUsers);
        }

        Log::info('Leftover calls assigned', [
            'total' => count($assignments)
        ]);

        return $assignments;
    }

    /**
     * Preview assignment without actually assigning calls
     * Returns what WOULD happen if we assign with this config
     *
     * @param string $targetDate
     * @param int $configId
     * @return array
     * @throws Exception
     */
    public function previewAssignment(string $targetDate, int $configId): array
    {
        try {
            Log::info('Previewing team level assignment', [
                'target_date' => $targetDate,
                'config_id' => $configId
            ]);

            // 1. Get config
            $config = TblCcTeamLevelConfig::find($configId);

            if (!$config) {
                throw new Exception("Config with ID {$configId} not found");
            }

            // ✅ Allow both suggested and approved configs for preview
            if (!in_array($config->configType, [TblCcTeamLevelConfig::TYPE_SUGGESTED, TblCcTeamLevelConfig::TYPE_APPROVED])) {
                throw new Exception("Invalid config type for preview");
            }

            // 2. Get duty roster agents grouped by level
            $agentsByLevel = $this->getAgentsByLevel($targetDate);

            if (empty(array_filter($agentsByLevel))) {
                throw new Exception("No agents found in duty roster for this date");
            }

            // 3. Get unassigned calls for batchId = 1, grouped by DPD
            $callsByDpd = $this->getUnassignedCallsByDpd();

            if ($callsByDpd->isEmpty()) {
                throw new Exception("No unassigned calls found for batch 1");
            }

            $totalCalls = $callsByDpd->flatten()->count();

            Log::info('Preview data loaded', [
                'total_calls' => $totalCalls,
                'dpd_groups' => $callsByDpd->keys()->toArray(),
                'agents_by_level' => array_map('count', $agentsByLevel)
            ]);

            // 4. Simulate assignment (WITHOUT actually updating database)
            $previewData = $this->simulateAssignment(
                $config,
                $agentsByLevel,
                $callsByDpd
            );

            Log::info('Team level assignment preview completed', [
                'total_calls' => $previewData['total_assigned'],
                'config_id' => $configId
            ]);

            return [
                'success' => true,
                'preview' => true,  // ✅ Flag to indicate this is preview
                'config' => [
                    'configId' => $config->configId,
                    'targetDate' => $config->targetDate->format('Y-m-d'),
                    'batchId' => $config->batchId,
                    'percentages' => [
                        'teamLeader' => (float) $config->teamLeaderPercentage,
                        'senior' => (float) $config->seniorPercentage,
                        'midLevel' => (float) $config->midLevelPercentage,
                        'junior' => (float) $config->juniorPercentage,
                    ],
                ],
                'total_assigned' => $previewData['total_assigned'],
                'assignments_by_level' => $previewData['assignments_by_level'],
                'assignments_by_user' => $previewData['assignments_by_user'],
            ];

        } catch (Exception $e) {
            Log::error('Team level assignment preview failed', [
                'target_date' => $targetDate,
                'config_id' => $configId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
