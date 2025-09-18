<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcPhoneCollection extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcPhoneCollection';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'phoneCollectionId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'assignedTo',
        'assignedBy',
        'assignedAt',
        'totalAttempts',
        'lastAttemptAt',
        'lastAttemptBy',
        'personCreated',
        'personUpdated',
        'contractId',
        'customerId',
        'paymentId',
        'paymentNo',
        'segmentType',
        'riskType',
        'assetId',
        'dueDate',
        'daysOverdueGross',
        'daysOverdueNet',
        'daysSinceLastPayment',
        'paymentAmount',
        'penaltyAmount',
        'totalAmount',
        'amountPaid',
        'amountUnpaid',
        'callPackageId',
        'personDeleted',
        'reasonDeleted',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'assignedAt' => 'datetime',
        'lastAttemptAt' => 'datetime',
        'dtCreated' => 'datetime',
        'dtUpdated' => 'datetime',
        'dtDeleted' => 'datetime',
        'dueDate' => 'date',
        'totalAttempts' => 'integer',
        'paymentNo' => 'integer',
        'daysOverdueGross' => 'integer',
        'daysOverdueNet' => 'integer',
        'daysSinceLastPayment' => 'integer',
        'paymentAmount' => 'integer',
        'penaltyAmount' => 'integer',
        'totalAmount' => 'integer',
        'amountPaid' => 'integer',
        'amountUnpaid' => 'integer',
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
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by assignedTo
     */
    public function scopeByAssignedTo($query, $assignedTo)
    {
        return $query->where('assignedTo', $assignedTo);
    }
}
