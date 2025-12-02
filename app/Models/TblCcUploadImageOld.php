<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TblCcUploadImageOld extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_CcUploadImage_Old';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'uploadImageId';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fileName',
        'fileType',
        'localUrl',
        'googleUrl',
        'googleUploadServiceLogId',
        'phoneCollectionDetailId',
        'createdBy',
        'updatedBy',
        'deletedBy',
        'deletedReason',
        'userCreatedBy',
        'userUpdatedBy',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    /**
     * Get the full URL for the uploaded image
     */
    public function getFullLocalUrl(): string
    {
        return url($this->localUrl);
    }

    /**
     * Relationship with TblCcPhoneCollectionDetail
     */
    public function phoneCollectionDetail()
    {
        return $this->belongsTo(
            TblCcPhoneCollectionDetail::class,
            'phoneCollectionDetailId',
            'phoneCollectionDetailId'
        );
    }
}
