<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcScript extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcScript';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'scriptId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'communicationToolId',
        'source',
        'segment',
        'receiver',
        'daysPastDueFrom',
        'dayPastDueTo',
        'scriptContentBur',
        'scriptContentEng',
        'scriptRemark',
        'scriptActive',
        'dtDeactivated',
        'personDeactivate',
        'createdBy',
        'updatedBy',
        'deletedBy',
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
        'scriptActive' => 'boolean',
        'daysPastDueFrom' => 'integer',
        'dayPastDueTo' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
        'dtDeactivated' => 'datetime',
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
     * Source constants
     */
    const SOURCE_NORMAL = 'normal';
    const SOURCE_DSLP = 'dslp';

    /**
     * Segment constants
     */
    const SEGMENT_PRE_DUE = 'pre-due';
    const SEGMENT_PAST_DUE = 'past-due';

    /**
     * Receiver constants
     */
    const RECEIVER_RPC = 'rpc';
    const RECEIVER_TPC = 'tpc';

    /**
     * Get all available sources
     */
    public static function getSources(): array
    {
        return [
            self::SOURCE_NORMAL,
            self::SOURCE_DSLP,
        ];
    }

    /**
     * Get all available segments
     */
    public static function getSegments(): array
    {
        return [
            self::SEGMENT_PRE_DUE,
            self::SEGMENT_PAST_DUE,
        ];
    }

    /**
     * Get all available receivers
     */
    public static function getReceivers(): array
    {
        return [
            self::RECEIVER_RPC,
            self::RECEIVER_TPC,
        ];
    }

    /**
     * Scope for active scripts only
     */
    public function scopeActive($query)
    {
        return $query->where('scriptActive', true);
    }

    /**
     * Scope for specific source
     */
    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope for specific segment
     */
    public function scopeBySegment($query, $segment)
    {
        return $query->where('segment', $segment);
    }

    /**
     * Scope for specific receiver
     */
    public function scopeByReceiver($query, $receiver)
    {
        return $query->where('receiver', $receiver);
    }

    /**
     * Scope for normal source
     */
    public function scopeNormal($query)
    {
        return $query->where('source', self::SOURCE_NORMAL);
    }

    /**
     * Scope for DSLP source
     */
    public function scopeDslp($query)
    {
        return $query->where('source', self::SOURCE_DSLP);
    }

    /**
     * Scope for pre-due segment
     */
    public function scopePreDue($query)
    {
        return $query->where('segment', self::SEGMENT_PRE_DUE);
    }

    /**
     * Scope for past-due segment
     */
    public function scopePastDue($query)
    {
        return $query->where('segment', self::SEGMENT_PAST_DUE);
    }

    /**
     * Scope for RPC receiver
     */
    public function scopeRpc($query)
    {
        return $query->where('receiver', self::RECEIVER_RPC);
    }

    /**
     * Scope for TPC receiver
     */
    public function scopeTpc($query)
    {
        return $query->where('receiver', self::RECEIVER_TPC);
    }

    /**
     * Scope for days past due range
     */
    public function scopeByDaysPastDue($query, $days)
    {
        return $query->where('daysPastDueFrom', '<=', $days)
                    ->where('dayPastDueTo', '>=', $days);
    }

    /**
     * Check if script is active
     */
    public function isActive(): bool
    {
        return $this->scriptActive;
    }

    /**
     * Check if script is deactivated
     */
    public function isDeactivated(): bool
    {
        return !is_null($this->dtDeactivated);
    }

    /**
     * Check if script is for normal source
     */
    public function isNormal(): bool
    {
        return $this->source === self::SOURCE_NORMAL;
    }

    /**
     * Check if script is for DSLP source
     */
    public function isDslp(): bool
    {
        return $this->source === self::SOURCE_DSLP;
    }

    /**
     * Check if script is for pre-due segment
     */
    public function isPreDue(): bool
    {
        return $this->segment === self::SEGMENT_PRE_DUE;
    }

    /**
     * Check if script is for past-due segment
     */
    public function isPastDue(): bool
    {
        return $this->segment === self::SEGMENT_PAST_DUE;
    }

    /**
     * Check if script is for RPC receiver
     */
    public function isRpc(): bool
    {
        return $this->receiver === self::RECEIVER_RPC;
    }

    /**
     * Check if script is for TPC receiver
     */
    public function isTpc(): bool
    {
        return $this->receiver === self::RECEIVER_TPC;
    }

    /**
     * Check if given days fall within this script's range
     */
    public function isInDaysRange(int $days): bool
    {
        return $days >= $this->daysPastDueFrom && $days <= $this->dayPastDueTo;
    }

    /**
     * Deactivate the script
     */
    public function deactivate(): void
    {
        $this->update([
            'scriptActive' => false,
            'dtDeactivated' => now(),
            // TODO: Set personDeactivate when auth is implemented
            // 'personDeactivate' => auth()->id(),
        ]);
    }

    /**
     * Reactivate the script
     */
    public function reactivate(): void
    {
        $this->update([
            'scriptActive' => true,
            'dtDeactivated' => null,
            'personDeactivate' => null,
        ]);
    }

    /**
     * Future relationship - Communication Tool
     * TODO: Uncomment when communication tools table is created
     */
    // public function communicationTool()
    // {
    //     return $this->belongsTo(CommunicationTool::class, 'communicationToolId', 'id');
    // }

    /**
     * Future relationships - Audit users
     * TODO: Uncomment when User model relationship is ready
     */
    // public function creator()
    // {
    //     return $this->belongsTo(User::class, 'personCreated', 'id');
    // }

    // public function updater()
    // {
    //     return $this->belongsTo(User::class, 'personUpdated', 'id');
    // }

    // public function deleter()
    // {
    //     return $this->belongsTo(User::class, 'personDeleted', 'id');
    // }

    // public function deactivator()
    // {
    //     return $this->belongsTo(User::class, 'personDeactivate', 'id');
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
