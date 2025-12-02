<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VwCcPhoneCollectionDetailRemarks extends Model
{
    protected $table = 'vw_ccphonecollectiondetail_remarks';
    protected $primaryKey = 'phoneCollectionDetailId';
    public $timestamps = false;

    protected $casts = [
        'createdAt' => 'datetime',
    ];

    public function standardRemark()
    {
        return $this->belongsTo(TblCcRemark::class, 'standardRemarkId', 'remarkId');
    }

    public function reason()
    {
        return $this->belongsTo(TblCcReason::class, 'reasonId', 'reasonId');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy', 'authUserId');
    }

    public function phoneCollection()
    {
        return $this->belongsTo(VwCcPhoneCollectionBasic::class, 'phoneCollectionId', 'phoneCollectionId');
    }

    public function uploadImages()
    {
        return $this->hasMany(TblCcUploadImage::class, 'phoneCollectionDetailId', 'phoneCollectionDetailId');
    }

    public function callResult()
    {
        return $this->belongsTo(TblCcCaseResult::class, 'callResultId', 'caseResultId');
    }

    public function uploadImagesOld()
    {
        return $this->hasMany(\App\Models\TblCcUploadImageOld::class, 'phoneCollectionDetailId', 'phoneCollectionDetailId')
            ->whereNull('tbl_CcUploadImage_Old.deletedAt');
    }
}
