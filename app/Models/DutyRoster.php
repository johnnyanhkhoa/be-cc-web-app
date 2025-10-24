<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DutyRoster extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'work_date',
        'user_id',
        'is_working',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected $casts = [
        'work_date' => 'date',
        'is_working' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationship: Duty roster belongs to a user (agent)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Duty roster created by a user (manager)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Get duty rosters for a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('work_date', $date);
    }

    /**
     * Scope: Get duty rosters for date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->where('work_date', '>=', $startDate)
                    ->where('work_date', '<=', $endDate);
    }

    /**
     * Scope: Get working agents only
     */
    public function scopeWorking($query)
    {
        return $query->where('is_working', true);
    }

    /**
     * Check if duty roster can be deleted (before 7AM same day)
     */
    public function canBeDeleted(): bool
    {
        $now = Carbon::now();
        $workDate = Carbon::parse($this->work_date);

        // Cannot delete past dates
        if ($workDate->isBefore($now->toDateString())) {
            return false;
        }

        // Cannot delete today after 7AM
        if ($workDate->isSameDay($now) && $now->hour >= 7) {
            return false;
        }

        return true;
    }

    /**
     * Static method: Get available agents for a specific date
     */
    public static function getAvailableAgentsForDate($date)
    {
        return static::with('user')
            ->forDate($date)
            ->working()
            ->get()
            ->pluck('user');
    }

    /**
     * Static method: Get duty roster data for date range
     */
    public static function getDutyRosterData($startDate, $endDate)
    {
        Log::info('Getting duty roster data', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $duties = static::with('user')
            ->where('work_date', '>=', $startDate)
            ->where('work_date', '<=', $endDate)
            ->where('is_working', true)
            ->get();

        Log::info('Duty roster query result', [
            'count' => $duties->count()
        ]);

        $groupedDuties = $duties->groupBy(function($duty) {
            return Carbon::parse($duty->work_date)->toDateString();
        });

        $result = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($current <= $end) {
            $dateStr = $current->toDateString();
            $agents = $groupedDuties->get($dateStr, collect())->map(function ($duty) {
                return [
                    'authUserId' => $duty->user->authUserId,
                    'userFullName' => $duty->user->userFullName, // ✅ ĐỔI TÊN
                    'email' => $duty->user->email,
                ];
            });

            $result[] = [
                'date' => $dateStr,
                'day_name' => $current->format('l'),
                'formatted_date' => $current->format('M d'),
                'agents' => $agents->values()->all(),
                'agent_count' => $agents->count(),
            ];

            $current->addDay();
        }

        return $result;
    }
}
