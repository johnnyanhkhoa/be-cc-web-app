<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TblCcBatch extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcBatch';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'batchId';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'code',
        'intensity',
        'batchActive',
        'deactivatedAt',
        'deactivatedBy',
        'createdBy',
        'updatedBy',
        'deletedBy',
        'deletedReason',
        'segmentType',
        'scriptCollectionId',
        'batchName',           // ← nếu chưa có
        'parentBatchId',       // ← THÊM
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'intensity' => 'json',
        'scriptCollectionId' => 'json',
        'batchActive' => 'boolean',
        'deactivatedAt' => 'datetime',
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
     * Scope for active batches only
     */
    public function scopeActive($query)
    {
        return $query->where('batchActive', true);
    }

    /**
     * Scope for specific segment type
     */
    public function scopeBySegmentType($query, $segmentType)
    {
        return $query->where('segmentType', $segmentType);
    }

    /**
     * Scope for batches with script collections
     */
    public function scopeHasScripts($query)
    {
        return $query->whereNotNull('scriptCollectionId');
    }

    /**
     * Get script IDs from scriptCollectionId JSON
     */
    public function getScriptIds(): array
    {
        if (!$this->scriptCollectionId || !isset($this->scriptCollectionId['scriptId'])) {
            return [];
        }

        return is_array($this->scriptCollectionId['scriptId'])
            ? $this->scriptCollectionId['scriptId']
            : [];
    }

    /**
     * Check if batch is active
     */
    public function isActive(): bool
    {
        return $this->batchActive === true;
    }

    /**
     * Check if batch has scripts
     */
    public function hasScripts(): bool
    {
        return !empty($this->getScriptIds());
    }

    /**
     * Deactivate the batch
     */
    public function deactivate(): void
    {
        $this->update([
            'batchActive' => false,
            'deactivatedAt' => now(),
            // TODO: Set deactivatedBy when auth is implemented
            // 'deactivatedBy' => auth()->id(),
        ]);
    }

    /**
     * Reactivate the batch
     */
    public function reactivate(): void
    {
        $this->update([
            'batchActive' => true,
            'deactivatedAt' => null,
            'deactivatedBy' => null,
        ]);
    }

    /**
     * Relationship with TblCcScript through scriptCollectionId
     * Note: This is not a direct foreign key relationship
     */
    public function scripts()
    {
        $scriptIds = $this->getScriptIds();

        if (empty($scriptIds)) {
            return TblCcScript::whereRaw('1 = 0'); // Return empty query
        }

        return TblCcScript::whereIn('scriptId', $scriptIds);
    }

    /**
     * Get active scripts for this batch
     */
    public function activeScripts()
    {
        return $this->scripts()->where('scriptActive', true);
    }

    /**
     * Boot the model to automatically set audit fields
     */
    protected static function boot()
    {
        parent::boot();

        // Set createdBy when creating
        static::creating(function ($model) {
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

        // Set deletedBy when soft deleting
        static::deleting(function ($model) {
            if ($model->isForceDeleting()) {
                return;
            }
            // TODO: Set from authenticated user when auth is implemented
            // $model->deletedBy = auth()->id();
            // $model->save();
        });
    }
}
