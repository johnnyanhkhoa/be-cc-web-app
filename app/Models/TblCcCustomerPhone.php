<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcCustomerPhone extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcCustomerPhone';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'phoneId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prospectId',
        'customerId',
        'householderId',
        'refereeId',
        'phoneNo',
        'contactType',
        'phoneStatus',
        'phoneType',
        'isPrimary',
        'isViber',
        'phoneRemark',
        'customerName',
        'phoneCollectionId',
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
        'prospectId' => 'integer',
        'customerId' => 'integer',
        'householderId' => 'integer',
        'refereeId' => 'integer',
        'phoneCollectionId' => 'integer',
        'isPrimary' => 'boolean',
        'isViber' => 'boolean',
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
     * Phone status constants
     */
    const PHONE_STATUS_ACTIVE = 'active';
    const PHONE_STATUS_INACTIVE = 'inactive';
    const PHONE_STATUS_WRONG = 'wrong';
    const PHONE_STATUS_DISCONNECTED = 'disconnected';

    /**
     * Phone type constants
     */
    const PHONE_TYPE_MOBILE = 'mobile';
    const PHONE_TYPE_LANDLINE = 'landline';

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
     * Get all available phone statuses
     */
    public static function getPhoneStatuses(): array
    {
        return [
            self::PHONE_STATUS_ACTIVE,
            self::PHONE_STATUS_INACTIVE,
            self::PHONE_STATUS_WRONG,
            self::PHONE_STATUS_DISCONNECTED,
        ];
    }

    /**
     * Get all available phone types
     */
    public static function getPhoneTypes(): array
    {
        return [
            self::PHONE_TYPE_MOBILE,
            self::PHONE_TYPE_LANDLINE,
        ];
    }

    /**
     * Scope: Filter by phoneCollectionId
     */
    public function scopeByPhoneCollectionId($query, $phoneCollectionId)
    {
        return $query->where('phoneCollectionId', $phoneCollectionId);
    }

    /**
     * Scope: Filter by customerId
     */
    public function scopeByCustomerId($query, $customerId)
    {
        return $query->where('customerId', $customerId);
    }

    /**
     * Scope: Filter by contactType
     */
    public function scopeByContactType($query, $contactType)
    {
        return $query->where('contactType', $contactType);
    }

    /**
     * Scope: Active phones only
     */
    public function scopeActive($query)
    {
        return $query->where('phoneStatus', self::PHONE_STATUS_ACTIVE);
    }

    /**
     * Scope: Primary phones only
     */
    public function scopePrimary($query)
    {
        return $query->where('isPrimary', true);
    }

    /**
     * Relationship with TblCcPhoneCollection
     */
    public function phoneCollection()
    {
        return $this->belongsTo(TblCcPhoneCollection::class, 'phoneCollectionId', 'phoneCollectionId');
    }
}
