<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcPMTGuideline extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcPMTGuideline';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'pmtId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pmtName',
        'pmtStep',
        'pmtRemark',
        'createdBy',
        'updatedBy',
        'deletedBy',
        'deletedReason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'pmtStep' => 'json',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'createdAt';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updatedAt';

    /**
     * The name of the "deleted at" column for soft deletes.
     *
     * @var string
     */
    const DELETED_AT = 'deletedAt';

    /**
     * Scope for searching by payment name
     */
    public function scopeByName($query, $pmtName)
    {
        return $query->where('pmtName', $pmtName);
    }

    /**
     * Scope for searching by partial payment name (case-insensitive)
     */
    public function scopeByNameLike($query, $pmtName)
    {
        return $query->where('pmtName', 'ILIKE', '%' . $pmtName . '%');
    }

    /**
     * Get payment steps as array
     */
    public function getStepsArray(): array
    {
        return $this->pmtStep ? (array) $this->pmtStep : [];
    }

    /**
     * Get formatted steps for display
     */
    public function getFormattedSteps(): array
    {
        $steps = $this->getStepsArray();
        $formatted = [];

        foreach ($steps as $key => $value) {
            $formatted[] = [
                'step' => $key,
                'instruction' => $value
            ];
        }

        return $formatted;
    }
}
