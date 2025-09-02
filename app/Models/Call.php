<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Call extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'call_id',
        'status',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'total_attempts',
        'last_attempt_at',
        'last_attempt_by',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected $casts = [
        'assigned_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'total_attempts' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The possible status values
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_REASSIGNED = 'reassigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ASSIGNED,
        self::STATUS_REASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Relationship: Call assigned to user (agent)
     */
    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relationship: Call assigned by user (manager)
     */
    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Relationship: Last attempt made by user
     */
    public function lastAttemptByUser()
    {
        return $this->belongsTo(User::class, 'last_attempt_by');
    }

    /**
     * Relationship: Call created by user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Call updated by user
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope: Get calls by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Get pending calls (not assigned)
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Get assigned calls
     */
    public function scopeAssigned($query)
    {
        return $query->whereIn('status', [self::STATUS_ASSIGNED, self::STATUS_REASSIGNED]);
    }

    /**
     * Scope: Get completed calls
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Get calls assigned to specific agent
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope: Get calls created within date range
     */
    public function scopeCreatedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if call can be reassigned
     */
    public function canBeReassigned(): bool
    {
        return in_array($this->status, [
            self::STATUS_ASSIGNED,
            self::STATUS_REASSIGNED,
            self::STATUS_IN_PROGRESS
        ]);
    }

    /**
     * Check if call is assignable (pending)
     */
    public function isAssignable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if call is completed or failed
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED
        ]);
    }

    /**
     * Assign call to agent
     */
    public function assignTo(int $agentId, int $assignedBy): void
    {
        $this->update([
            'assigned_to' => $agentId,
            'assigned_by' => $assignedBy,
            'assigned_at' => now(),
            'status' => self::STATUS_ASSIGNED,
            'updated_by' => $assignedBy,
        ]);
    }

    /**
     * Reassign call to different agent
     */
    public function reassignTo(int $newAgentId, int $reassignedBy): void
    {
        $this->update([
            'assigned_to' => $newAgentId,
            'assigned_by' => $reassignedBy,
            'assigned_at' => now(),
            'status' => self::STATUS_REASSIGNED,
            'updated_by' => $reassignedBy,
        ]);
    }

    /**
     * Mark call as completed
     */
    public function markCompleted(int $completedBy): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'updated_by' => $completedBy,
        ]);
    }

    /**
     * Record call attempt
     */
    public function recordAttempt(int $attemptBy): void
    {
        $this->increment('total_attempts');
        $this->update([
            'last_attempt_at' => now(),
            'last_attempt_by' => $attemptBy,
            'status' => self::STATUS_IN_PROGRESS,
            'updated_by' => $attemptBy,
        ]);
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ASSIGNED => 'Assigned',
            self::STATUS_REASSIGNED => 'Reassigned',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    /**
     * Static method: Get available calls for assignment
     */
    public static function getAvailableForAssignment()
    {
        return static::pending()->get();
    }

    /**
     * Static method: Get calls assigned to agents for specific date
     */
    public static function getAssignedCallsForDate($date)
    {
        return static::assigned()
            ->with(['assignedAgent', 'assignedByUser'])
            ->whereDate('assigned_at', $date)
            ->get();
    }
}
