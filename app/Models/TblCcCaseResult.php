<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcCaseResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tbl_CcCaseResult';
    protected $primaryKey = 'caseResultId';

    protected $fillable = [
        'caseResultName',
        'caseResultRemark',
        'contactType',
        'batchId',
        'requiredField',
        'caseResultActive',      // ← THÊM
        'createdBy',
        'updatedBy',
        'deletedBy',
        'deactivatedAt',         // ← THÊM
        'deactivatedBy',         // ← THÊM
    ];

    protected $casts = [
        'requiredField' => 'array',
        'caseResultActive' => 'boolean',  // ← THÊM
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
        'deactivatedAt' => 'datetime',    // ← THÊM
    ];

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';

    /**
     * Contact type constants
     */
    const CONTACT_TYPE_ALL = 'all';
    const CONTACT_TYPE_RPC = 'rpc';
    const CONTACT_TYPE_TPC = 'tpc';
    const CONTACT_TYPE_RB = 'rb';

    /**
     * Get all available contact types
     */
    public static function getContactTypes(): array
    {
        return [
            self::CONTACT_TYPE_ALL,
            self::CONTACT_TYPE_RPC,
            self::CONTACT_TYPE_TPC,
            self::CONTACT_TYPE_RB,
        ];
    }

    /**
     * Scope for active case results (not deleted)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deletedAt');
    }

    /**
     * Scope for active AND enabled case results
     */
    public function scopeActiveAndEnabled($query)
    {
        return $query->whereNull('deletedAt')
                     ->where('caseResultActive', true);
    }

    /**
     * Scope for specific contact type
     */
    public function scopeByContactType($query, $contactType)
    {
        return $query->where('contactType', $contactType);
    }

    /**
     * Scope for specific batch
     */
    public function scopeByBatch($query, $batchId)
    {
        return $query->where('batchId', $batchId);
    }

    /**
     * Relationship with Batch
     */
    public function batch()
    {
        return $this->belongsTo(TblCcBatch::class, 'batchId', 'batchId');
    }
}
