<?php

namespace App\Services;

use App\Models\TblCcUserLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class UserLevelService
{
    /**
     * Set user level for a batch (create or update)
     *
     * @param int $userId
     * @param int $batchId
     * @param string $level
     * @param int $actionBy User ID who performs this action
     * @return TblCcUserLevel
     * @throws Exception
     */
    public function setUserLevel(int $userId, int $batchId, string $level, int $actionBy): TblCcUserLevel
    {
        DB::beginTransaction();

        try {
            // Check if user exists
            $user = User::find($userId);
            if (!$user) {
                throw new Exception("User with ID {$userId} not found");
            }

            // Check if user already has an active level for this batch
            $existingLevel = TblCcUserLevel::where('userId', $userId)
                ->where('batchId', $batchId)
                ->where('isActive', true)
                ->first();

            if ($existingLevel) {
                // User already has a level - this is an UPDATE
                if ($existingLevel->level === $level) {
                    throw new Exception("User already has level '{$level}' for batch {$batchId}");
                }

                Log::info('Updating user level', [
                    'user_id' => $userId,
                    'batch_id' => $batchId,
                    'old_level' => $existingLevel->level,
                    'new_level' => $level
                ]);

                // Deactivate old level (WITH updatedAt and updatedBy)
                $existingLevel->isActive = false;
                $existingLevel->updatedBy = $actionBy;
                $existingLevel->updatedAt = now();
                $existingLevel->save();

                // Create new level record (WITHOUT updatedAt and updatedBy)
                $newLevel = new TblCcUserLevel();
                $newLevel->timestamps = false; // ✅ Disable auto timestamps

                $newLevel->userId = $userId;
                $newLevel->batchId = $batchId;
                $newLevel->level = $level;
                $newLevel->isActive = true;
                $newLevel->createdBy = $actionBy;
                $newLevel->createdAt = now();
                // updatedAt and updatedBy will be NULL

                $newLevel->save();

            } else {
                // User doesn't have a level yet - this is a CREATE
                Log::info('Creating user level', [
                    'user_id' => $userId,
                    'batch_id' => $batchId,
                    'level' => $level
                ]);

                // Create new level record (WITHOUT updatedAt and updatedBy)
                $newLevel = new TblCcUserLevel();
                $newLevel->timestamps = false; // ✅ Disable auto timestamps

                $newLevel->userId = $userId;
                $newLevel->batchId = $batchId;
                $newLevel->level = $level;
                $newLevel->isActive = true;
                $newLevel->createdBy = $actionBy;
                $newLevel->createdAt = now();
                // updatedAt and updatedBy will be NULL

                $newLevel->save();
            }

            DB::commit();

            return $newLevel->fresh(['user', 'creator']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to set user level', [
                'user_id' => $userId,
                'batch_id' => $batchId,
                'level' => $level,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get users by level for a specific batch
     *
     * @param int $batchId
     * @param string|null $level
     * @param bool|null $isActive
     * @return \Illuminate\Support\Collection
     */
    public function getUsersByLevel(int $batchId, ?string $level = null, ?bool $isActive = null)
    {
        $query = TblCcUserLevel::with(['user', 'creator', 'updater'])
            ->where('batchId', $batchId);

        if ($level !== null) {
            $query->where('level', $level);
        }

        if ($isActive !== null) {
            $query->where('isActive', $isActive);
        }

        return $query->orderBy('createdAt', 'desc')->get();
    }

    /**
     * Get level history for a user in a specific batch
     *
     * @param int $userId
     * @param int $batchId
     * @return \Illuminate\Support\Collection
     */
    public function getUserLevelHistory(int $userId, int $batchId)
    {
        return TblCcUserLevel::with(['creator', 'updater'])
            ->where('userId', $userId)
            ->where('batchId', $batchId)
            ->orderBy('createdAt', 'desc')
            ->get();
    }

    /**
     * Batch update user levels
     *
     * @param int $batchId
     * @param array $updates Format: [['userId' => 1, 'level' => 'senior'], ...]
     * @param int $actionBy
     * @return array
     */
    public function batchUpdateLevels(int $batchId, array $updates, int $actionBy): array
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($updates as $update) {
            try {
                $userId = $update['userId'];
                $level = $update['level'];

                $newLevel = $this->setUserLevel($userId, $batchId, $level, $actionBy);

                $results[] = [
                    'userId' => $userId,
                    'level' => $level,
                    'success' => true,
                ];
                $successCount++;

            } catch (Exception $e) {
                $results[] = [
                    'userId' => $update['userId'] ?? null,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                $failCount++;
            }
        }

        return [
            'results' => $results,
            'summary' => [
                'total' => count($updates),
                'successful' => $successCount,
                'failed' => $failCount,
            ],
        ];
    }
}
