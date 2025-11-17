<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TblCcPromiseHistory extends Model
{
    protected $table = 'tbl_CcPromiseHistory';
    protected $primaryKey = 'promiseHistoryId';
    public $timestamps = false;

    protected $fillable = [
        'contractId',
        'phoneCollectionId',
        'phoneCollectionDetailId',
        'paymentId',
        'promiseType',
        'promisedPaymentDate',
        'dtCallLater',
        'isActive',
        'createdAt',
        'createdBy',
        'updatedAt',
        'updatedBy',
        'deletedAt',
        'deletedBy',
        'deletedReason',
    ];

    protected $casts = [
        'promisedPaymentDate' => 'date',
        'dtCallLater' => 'datetime',
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function phoneCollection()
    {
        return $this->belongsTo(TblCcPhoneCollection::class, 'phoneCollectionId', 'phoneCollectionId');
    }

    public function phoneCollectionDetail()
    {
        return $this->belongsTo(TblCcPhoneCollectionDetail::class, 'phoneCollectionDetailId', 'phoneCollectionDetailId');
    }
}
