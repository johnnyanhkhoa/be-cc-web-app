<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcCaseResult extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcCaseResult';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'caseResultId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'caseResultName',
        'escalationRemark',
        'caseResultRemark',
        'preDue',
        'pastDue',
        'escalation',
        'specialCase',
        'dslp',
        'personCreated',
        'personUpdated',
        'personDeleted',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'preDue' => 'boolean',
        'pastDue' => 'boolean',
        'escalation' => 'boolean',
        'specialCase' => 'boolean',
        'dslp' => 'boolean',
        'dtCreated' => 'datetime',
        'dtUpdated' => 'datetime',
        'dtDeleted' => 'datetime',
    ];

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'dtCreated';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'dtUpdated';

    /**
     * The name of the "deleted at" column for soft deletes.
     *
     * @var string
     */
    const DELETED_AT = 'dtDeleted';

    /**
     * Scope for active case results (not deleted)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('dtDeleted');
    }

    /**
     * Scope for pre-due cases
     */
    public function scopePreDue($query)
    {
        return $query->where('preDue', true);
    }

    /**
     * Scope for past-due cases
     */
    public function scopePastDue($query)
    {
        return $query->where('pastDue', true);
    }

    /**
     * Scope for escalation cases
     */
    public function scopeEscalation($query)
    {
        return $query->where('escalation', true);
    }

    /**
     * Scope for special cases
     */
    public function scopeSpecialCase($query)
    {
        return $query->where('specialCase', true);
    }

    /**
     * Scope for DSLP cases
     */
    public function scopeDslp($query)
    {
        return $query->where('dslp', true);
    }
}
