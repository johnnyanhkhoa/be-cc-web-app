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
        'createdBy',
        'updatedBy',
        'segmentType',
        'contractId',
        'customerId',
        'paymentId',
        'paymentNo',
        'riskType',
        'assetId',
        'dueDate',
        'daysOverdueGross',
        'daysOverdueNet',
        'daysSinceLastPayment',
        'lastPaymentDate',
        'paymentAmount',
        'penaltyAmount',
        'totalAmount',
        'amountPaid',
        'amountUnpaid',
        'contractNo',
        'contractDate',
        'contractType',
        'contractingProductType',
        'customerFullName',
        'gender',
        'birthDate',
        'callPackageId',
        'deletedBy',
        'deletedReason',
        'batchId',
        'riskType',
        'completedBy',
        'completedAt',
        'hasKYCAppAccount',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'assignedAt' => 'datetime',
        'lastAttemptAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
        'dueDate' => 'date',
        'lastPaymentDate' => 'date',
        'contractDate' => 'date',
        'birthDate' => 'date',
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
        'completedAt' => 'datetime',
        'hasKYCAppAccount' => 'boolean',
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

    /**
     * Relationship with TblCcBatch
     */
    public function batch()
    {
        return $this->belongsTo(TblCcBatch::class, 'batchId', 'batchId');
    }

    /**
     * Scope to filter by assignedAt date
     */
    public function scopeByAssignedAt($query, $assignedAt)
    {
        // Query: assignedAt=2025-10-24
        // Nghĩa là: lấy tất cả records mà khi convert sang Asia/Yangon thì DATE = 2025-10-24

        return $query->whereRaw(
            "DATE(\"assignedAt\" AT TIME ZONE 'Asia/Yangon') = ?",
            [$assignedAt]
        );
    }
}
