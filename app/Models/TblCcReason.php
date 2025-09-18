<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcReason extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcReason';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'reasonId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reasonType',
        'reasonName',
        'reasonActive',
        'reasonRemark',
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
        'reasonActive' => 'boolean',
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
     * Scope for active reasons only
     */
    public function scopeActive($query)
    {
        return $query->where('reasonActive', true);
    }

    /**
     * Scope for specific reason type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('reasonType', $type);
    }

    /**
     * Check if reason is active
     */
    public function isActive(): bool
    {
        return $this->reasonActive;
    }

    /**
     * Get the user who created this record
     * TODO: Uncomment when User model relationship is ready
     */
    // public function creator()
    // {
    //     return $this->belongsTo(User::class, 'personCreated', 'id');
    // }

    /**
     * Get the user who last updated this record
     * TODO: Uncomment when User model relationship is ready
     */
    // public function updater()
    // {
    //     return $this->belongsTo(User::class, 'personUpdated', 'id');
    // }

    /**
     * Get the user who deleted this record
     * TODO: Uncomment when User model relationship is ready
     */
    // public function deleter()
    // {
    //     return $this->belongsTo(User::class, 'personDeleted', 'id');
    // }

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
