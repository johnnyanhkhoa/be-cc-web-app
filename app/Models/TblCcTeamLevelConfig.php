<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TblCcTeamLevelConfig extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tbl_CcTeamLevelConfig';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'configId';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'targetDate',
        'teamLeaderCount',
        'seniorCount',
        'midLevelCount',
        'juniorCount',
        'totalAgents',
        'teamLeaderPercentage',
        'seniorPercentage',
        'midLevelPercentage',
        'juniorPercentage',
        'configType',
        'isActive',
        'remarks',
        'basedOnConfigId',
        'createdBy',
        'updatedBy',
        'approvedBy',
        'approvedAt',
        'totalCalls',  // ← THÊM
        'batchId',     // ← THÊM
        'isAssigned',
        'assignmentsByUser',
        'assignedBy',   // ← THÊM
        'assignedAt',   // ← THÊM
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'targetDate' => 'date',
        'teamLeaderCount' => 'integer',
        'seniorCount' => 'integer',
        'midLevelCount' => 'integer',
        'juniorCount' => 'integer',
        'totalAgents' => 'integer',
        'totalCalls' => 'integer',  // ← THÊM
        'batchId' => 'integer',
        'teamLeaderPercentage' => 'decimal:2',
        'seniorPercentage' => 'decimal:2',
        'midLevelPercentage' => 'decimal:2',
        'juniorPercentage' => 'decimal:2',
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'approvedAt' => 'datetime',
        'assignedAt' => 'datetime',
        'isAssigned' => 'boolean',
        'assignmentsByUser' => 'array',
    ];

    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'createdAt';

    /**
     * The name of the "updated at" column.
     */
    const UPDATED_AT = 'updatedAt';

    /**
     * Config types
     */
    const TYPE_SUGGESTED = 'suggested';
    const TYPE_APPROVED = 'approved';

    /**
     * Relationship: Config based on previous config
     */
    public function basedOnConfig()
    {
        return $this->belongsTo(TblCcTeamLevelConfig::class, 'basedOnConfigId', 'configId');
    }

    /**
     * Relationship: Creator user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy', 'id');
    }

    /**
     * Relationship: Updater user
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updatedBy', 'id');
    }

    /**
     * Relationship: Approver user
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approvedBy', 'id');
    }

    /**
     * Scope: Filter by target date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('targetDate', $date);
    }

    /**
     * Scope: Filter by config type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('configType', $type);
    }

    /**
     * Scope: Only active configs
     */
    public function scopeActive($query)
    {
        return $query->where('isActive', true);
    }

    /**
     * Scope: Suggested configs
     */
    public function scopeSuggested($query)
    {
        return $query->where('configType', self::TYPE_SUGGESTED);
    }

    /**
     * Scope: Approved configs
     */
    public function scopeApproved($query)
    {
        return $query->where('configType', self::TYPE_APPROVED);
    }

    /**
     * Relationship: Get assigner (user who assigned calls)
     */
    public function assigner()
    {
        return $this->belongsTo(User::class, 'assignedBy', 'id');
    }
}
