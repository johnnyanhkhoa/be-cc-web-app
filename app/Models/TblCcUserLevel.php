<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TblCcUserLevel extends Model
{
    use HasFactory;

    protected $table = 'tbl_CcUserLevel';
    protected $primaryKey = 'userLevelId';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'batchId',
        'level',
        'isActive',
        'createdBy',
        'updatedBy',
    ];

    protected $casts = [
        'userId' => 'integer',
        'batchId' => 'integer',
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    // Level constants
    const LEVEL_TEAM_LEADER = 'team-leader';
    const LEVEL_SENIOR = 'senior';
    const LEVEL_MID_LEVEL = 'mid-level';
    const LEVEL_JUNIOR = 'junior';
    const LEVEL_NEW_JOINER = 'new-joiner';

    /**
     * Relationship: User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    /**
     * Relationship: Creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy', 'id');
    }

    /**
     * Relationship: Updater
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updatedBy', 'id');
    }

    /**
     * Scope: Active levels only
     */
    public function scopeActive($query)
    {
        return $query->where('isActive', true);
    }

    /**
     * Scope: Inactive levels only
     */
    public function scopeInactive($query)
    {
        return $query->where('isActive', false);
    }

    /**
     * Scope: Filter by batch
     */
    public function scopeForBatch($query, int $batchId)
    {
        return $query->where('batchId', $batchId);
    }

    /**
     * Scope: Filter by level
     */
    public function scopeOfLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get active level for user in specific batch
     */
    public static function getActiveLevel(int $userId, int $batchId): ?string
    {
        $record = static::where('userId', $userId)
            ->where('batchId', $batchId)
            ->where('isActive', true)
            ->first();

        return $record?->level;
    }
}
