<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcPhoneCollectionDetail extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcPhoneCollectionDetail';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'phoneCollectionDetailId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'phoneCollectionId', // NEW FIELD
        'contactType',
        'phoneId',
        'contactDetailId',
        'contactPhoneNumer',
        'contactName',
        'contactRelation',
        'callStatus',
        'callResultId',
        'leaveMessage',
        'remark',
        'promisedPaymentDate',
        'askingPostponePayment',
        'dtCallLater',
        'dtCallStarted',
        'dtCallEnded',
        'updatePhoneRequest',
        'updatePhoneRemark',
        'standardRemarkId',
        'standardRemarkContent',
        'reschedulingEvidence',
        'uploadDocuments',
        'createdBy',
        'updatedBy',
        'deletedBy',
        'deletedReason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'askingPostponePayment' => 'boolean',
        'updatePhoneRequest' => 'boolean',
        'reschedulingEvidence' => 'boolean',
        'uploadDocuments' => 'json',
        'promisedPaymentDate' => 'date',
        'dtCallLater' => 'date',
        'dtCallStarted' => 'datetime',
        'dtCallEnded' => 'datetime',
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
     * Contact type constants
     */
    const CONTACT_TYPE_RPC = 'rpc';
    const CONTACT_TYPE_TPC = 'tpc';
    const CONTACT_TYPE_RB = 'rb';

    /**
     * Call status constants
     */
    const CALL_STATUS_REACHED = 'reached';
    const CALL_STATUS_RING = 'ring';
    const CALL_STATUS_BUSY = 'busy';
    const CALL_STATUS_CANCELLED = 'cancelled';
    const CALL_STATUS_POWER_OFF = 'power_off';
    const CALL_STATUS_WRONG_NUMBER = 'wrong_number';
    const CALL_STATUS_NO_CONTACT = 'no_contact';

    /**
     * Get all available contact types
     */
    public static function getContactTypes(): array
    {
        return [
            self::CONTACT_TYPE_RPC,
            self::CONTACT_TYPE_TPC,
            self::CONTACT_TYPE_RB,
        ];
    }

    /**
     * Get all available call statuses
     */
    public static function getCallStatuses(): array
    {
        return [
            self::CALL_STATUS_REACHED,
            self::CALL_STATUS_RING,
            self::CALL_STATUS_BUSY,
            self::CALL_STATUS_CANCELLED,
            self::CALL_STATUS_POWER_OFF,
            self::CALL_STATUS_WRONG_NUMBER,
            self::CALL_STATUS_NO_CONTACT,
        ];
    }

    /**
     * Relationship with TblCcPhoneCollection
     */
    public function phoneCollection()
    {
        return $this->belongsTo(TblCcPhoneCollection::class, 'phoneCollectionId', 'phoneCollectionId');
    }

    /**
     * Relationship with TblCcRemark
     */
    public function standardRemark()
    {
        return $this->belongsTo(TblCcRemark::class, 'standardRemarkId', 'remarkId');
    }

    /**
     * Relationship with TblCcCaseResult (when created)
     */
    public function callResult()
    {
        return $this->belongsTo(TblCcCaseResult::class, 'callResultId', 'caseResultId');
    }

    /**
     * Relationship with User (creator)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy', 'id');
    }

    /**
     * Scope: Filter by phoneCollectionId
     */
    public function scopeByPhoneCollectionId($query, $phoneCollectionId)
    {
        return $query->where('phoneCollectionId', $phoneCollectionId);
    }

    /**
     * Scope: Filter by contact type
     */
    public function scopeByContactType($query, $contactType)
    {
        return $query->where('contactType', $contactType);
    }

    /**
     * Scope: Filter by call status
     */
    public function scopeByCallStatus($query, $callStatus)
    {
        return $query->where('callStatus', $callStatus);
    }

    /**
     * Boot the model to automatically set audit fields
     */
    protected static function boot()
    {
        parent::boot();

        // Set createdBy when creating
        static::creating(function ($model) {
            // Auto set createdAt
            if (!$model->createdAt) {
                $model->createdAt = now();
            }

            // TODO: Set from authenticated user when auth is implemented
            // if (!$model->createdBy && auth()->check()) {
            //     $model->createdBy = auth()->id();
            // }
        });

        // Set updatedBy when updating
        static::updating(function ($model) {
            // TODO: Set from authenticated user when auth is implemented
            // $model->updatedBy = auth()->id();
        });
    }
}
