<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcBatch extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_CcBatch';
    protected $primaryKey = 'batchId';

    protected $fillable = [
        'type',
        'code',
        'intensity',
        'batchActive',
        'segmentType',
        'createdBy',
        'updatedBy'
    ];

    protected $casts = [
        'intensity' => 'array',
        'batchActive' => 'boolean',
        'deactivatedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime'
    ];

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';

    public function scopeActive($query)
    {
        return $query->where('batchActive', true);
    }

    public function scopeBySegmentType($query, $segmentType)
    {
        return $query->where('segmentType', $segmentType);
    }
}
