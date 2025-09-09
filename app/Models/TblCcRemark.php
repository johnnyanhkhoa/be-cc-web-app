<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcRemark extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_cc_remark';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'remarkId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'remarkContent',
        'contactType',
        'remarkActive',
        'personCreated',
        'personUpdated',
        'personDeleted',
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
        'remarkActive' => 'boolean',
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
     * Contact type constants
     */
    const CONTACT_TYPE_ALL = 'all';
    const CONTACT_TYPE_RPC = 'rpc';
    const CONTACT_TYPE_TPC = 'tpc';

    /**
     * Get all available contact types
     */
    public static function getContactTypes(): array
    {
        return [
            self::CONTACT_TYPE_ALL,
            self::CONTACT_TYPE_RPC,
            self::CONTACT_TYPE_TPC,
        ];
    }

    /**
     * Scope for active remarks only
     */
    public function scopeActive($query)
    {
        return $query->where('remarkActive', true);
    }

    /**
     * Scope for specific contact type
     */
    public function scopeByContactType($query, $type)
    {
        return $query->where('contactType', $type);
    }

    /**
     * Scope for RPC contact type
     */
    public function scopeRpc($query)
    {
        return $query->where('contactType', self::CONTACT_TYPE_RPC);
    }

    /**
     * Scope for TPC contact type
     */
    public function scopeTpc($query)
    {
        return $query->where('contactType', self::CONTACT_TYPE_TPC);
    }

    /**
     * Scope for ALL contact type
     */
    public function scopeAll($query)
    {
        return $query->where('contactType', self::CONTACT_TYPE_ALL);
    }

    /**
     * Check if remark is active
     */
    public function isActive(): bool
    {
        return $this->remarkActive;
    }

    /**
     * Check if remark is for RPC
     */
    public function isRpc(): bool
    {
        return $this->contactType === self::CONTACT_TYPE_RPC;
    }

    /**
     * Check if remark is for TPC
     */
    public function isTpc(): bool
    {
        return $this->contactType === self::CONTACT_TYPE_TPC;
    }

    /**
     * Check if remark is for ALL
     */
    public function isAll(): bool
    {
        return $this->contactType === self::CONTACT_TYPE_ALL;
    }

    /**
     * Boot the model to automatically set audit fields
     */
    protected static function boot()
    {
        parent::boot();

        // Set personCreated when creating
        static::creating(function ($model) {
            // TODO: Set from authenticated user when auth is implemented
            // $model->personCreated = auth()->id();
        });

        // Set personUpdated when updating
        static::updating(function ($model) {
            // TODO: Set from authenticated user when auth is implemented
            // $model->personUpdated = auth()->id();
        });

        // Set personDeleted when soft deleting
        static::deleting(function ($model) {
            if ($model->isForceDeleting()) {
                return;
            }
            // TODO: Set from authenticated user when auth is implemented
            // $model->personDeleted = auth()->id();
            // $model->save();
        });
    }
}
