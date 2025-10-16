<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property-read \App\Models\User|null $assignedAgent
 * @property-read \App\Models\User|null $assignedByUser
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $lastAttemptByUser
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call assigned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call assignedTo($userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call createdBetween($startDate, $endDate)
 * @method static \Database\Factories\CallFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call withStatus($status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Call withoutTrashed()
 */
	class Call extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $work_date
 * @property int $user_id
 * @property bool $is_working
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster forDate($date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster forDateRange($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster whereIsWorking($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster whereWorkDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster withoutTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DutyRoster working()
 */
	class DutyRoster extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $caseId
 * @property string|null $phoneNo
 * @property string|null $phoneExtension
 * @property float|null $userId
 * @property string|null $username
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereCaseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog wherePhoneExtension($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog wherePhoneNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcAsteriskCallLog withoutTrashed()
 */
	class TblCcAsteriskCallLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $batchId
 * @property string|null $type enum 'sms', 'phone-collection'
 * @property string|null $code enum predue, pastdue, dslp, gps-dpd1_30, gps-dpd31_180
 * @property array<array-key, mixed>|null $intensity
 * @property bool|null $batchActive
 * @property \Illuminate\Support\Carbon|null $deactivatedAt
 * @property int|null $deactivatedBy
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @property string|null $segmentType
 * @property array<array-key, mixed>|null $scriptCollectionId
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch bySegmentType($segmentType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch hasScripts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereBatchActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereDeactivatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereDeactivatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereIntensity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereScriptCollectionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereSegmentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcBatch withoutTrashed()
 */
	class TblCcBatch extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $caseResultId
 * @property string|null $caseResultName
 * @property string|null $caseResultRemark
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedBy
 * @property string|null $contactType
 * @property int $batchId
 * @property array<array-key, mixed>|null $requiredField
 * @property-read \App\Models\TblCcBatch $batch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult byBatch($batchId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult byContactType($contactType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereCaseResultId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereCaseResultName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereCaseResultRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereContactType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereRequiredField($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcCaseResult withoutTrashed()
 */
	class TblCcCaseResult extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $pmtId
 * @property string|null $pmtName
 * @property array<array-key, mixed>|null $pmtStep
 * @property string|null $pmtRemark
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline byName($pmtName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline byNameLike($pmtName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline wherePmtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline wherePmtName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline wherePmtRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline wherePmtStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPMTGuideline withoutTrashed()
 */
	class TblCcPMTGuideline extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int|null $phoneCollectionId
 * @property string|null $status
 * @property int|null $assignedTo
 * @property int|null $assignedBy
 * @property \Illuminate\Support\Carbon|null $assignedAt
 * @property int|null $totalAttempts
 * @property \Illuminate\Support\Carbon|null $lastAttemptAt
 * @property int|null $lastAttemptBy
 * @property int|null $createdBy
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $contractId
 * @property int|null $customerId
 * @property int|null $paymentId
 * @property int|null $paymentNo
 * @property string|null $segmentType pre-due,past-due
 * @property int|null $assetId
 * @property \Illuminate\Support\Carbon|null $dueDate
 * @property int|null $daysOverdueGross
 * @property int|null $daysOverdueNet
 * @property int|null $daysSinceLastPayment
 * @property int|null $paymentAmount
 * @property int|null $penaltyAmount
 * @property int|null $totalAmount
 * @property int|null $amountPaid
 * @property int|null $amountUnpaid
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @property \Illuminate\Support\Carbon|null $lastPaymentDate
 * @property string|null $contractNo
 * @property \Illuminate\Support\Carbon|null $contractDate
 * @property string|null $contractType
 * @property string|null $contractingProductType
 * @property string|null $customerFullName
 * @property string|null $gender
 * @property \Illuminate\Support\Carbon|null $birthDate
 * @property int|null $batchId
 * @property string|null $riskType
 * @property-read \App\Models\TblCcBatch|null $batch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection byAssignedTo($assignedTo)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection byStatus($status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereAmountUnpaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereAssignedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereAssignedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereContractDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereContractNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereContractType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereContractingProductType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereCustomerFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereDaysOverdueGross($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereDaysOverdueNet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereDaysSinceLastPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereLastAttemptAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereLastAttemptBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereLastPaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection wherePaymentAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection wherePaymentNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection wherePenaltyAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection wherePhoneCollectionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereRiskType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereSegmentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereTotalAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollection withoutTrashed()
 */
	class TblCcPhoneCollection extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $phoneCollectionDetailId
 * @property string|null $contactType enum('rpc','tpc')
 * @property int|null $phoneId Nếu là contact của RPC thì ở đây được lưu ID và không null. Và ngược lại.
 * @property int|null $contactDetailId Để lưu thông tin reference trong bảng contact detail này
 * @property string|null $contactPhoneNumer Số điện thoại lấy được nếu trong cuộc gọi thì customer cung cấp mới
 * @property string|null $contactName Tên được cung cấp mới đi kèm contact_phone_number khi trong cuộc gọi được cung cấp
 * @property string|null $contactRelation Mối quan hệ giữa contact mới này với customer chính
 * @property string|null $callStatus 'reached','ring','busy','cancelled','power_off','wrong_number','no_contact'
 * @property int|null $callResultId Tham khảo và tạo lại bảng CcCaseResult để tạo lại 1 bảng và gắn tới id trong bảng đó. Các value trong đó là các lựa chọn khi gọi tới khách hàng ở call widget
 * @property string|null $leaveMessage
 * @property string|null $remark
 * @property \Illuminate\Support\Carbon|null $promisedPaymentDate
 * @property bool|null $askingPostponePayment
 * @property \Illuminate\Support\Carbon|null $dtCallLater
 * @property \Illuminate\Support\Carbon|null $dtCallStarted
 * @property \Illuminate\Support\Carbon|null $dtCallEnded
 * @property bool|null $updatePhoneRequest
 * @property string|null $updatePhoneRemark
 * @property int|null $standardRemarkId Foreign key tới tbl_cc_remark
 * @property string|null $standardRemarkContent Để lưu remark mới để chi tiết hơn nội dung ở trong bảng ccRemark
 * @property bool|null $reschedulingEvidence
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedby
 * @property string|null $deletedReason
 * @property int|null $phoneCollectionId
 * @property-read \App\Models\TblCcCaseResult|null $callResult
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\TblCcPhoneCollection|null $phoneCollection
 * @property-read \App\Models\TblCcRemark|null $standardRemark
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TblCcUploadImage> $uploadImages
 * @property-read int|null $upload_images_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail byCallStatus($callStatus)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail byContactType($contactType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail byPhoneCollectionId($phoneCollectionId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereAskingPostponePayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereCallResultId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereCallStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereContactDetailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereContactPhoneNumer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereContactRelation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereContactType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereDeletedby($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereDtCallEnded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereDtCallLater($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereDtCallStarted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereLeaveMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail wherePhoneCollectionDetailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail wherePhoneCollectionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail wherePhoneId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail wherePromisedPaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereReschedulingEvidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereStandardRemarkContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereStandardRemarkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereUpdatePhoneRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereUpdatePhoneRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcPhoneCollectionDetail withoutTrashed()
 */
	class TblCcPhoneCollectionDetail extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int|null $reasonId
 * @property string|null $reasonType
 * @property string|null $reasonName
 * @property bool|null $reasonActive
 * @property string|null $reasonRemark
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason byType($type)
 * @method static \Database\Factories\TblCcReasonFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereReasonActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereReasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereReasonName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereReasonRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereReasonType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcReason withoutTrashed()
 */
	class TblCcReason extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int|null $remarkId
 * @property string|null $remarkContent
 * @property string|null $contactType
 * @property bool|null $remarkActive
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark all()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark byContactType($type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark rpc()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark tpc()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereContactType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereRemarkActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereRemarkContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereRemarkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcRemark withoutTrashed()
 */
	class TblCcRemark extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $scriptId
 * @property string|null $receiver enum('rpc','tpc')
 * @property int|null $daysPastDueFrom
 * @property int|null $daysPastDueTo
 * @property string|null $scriptContentBur
 * @property string|null $scriptContentEng
 * @property string|null $scriptRemark
 * @property bool|null $scriptActive
 * @property \Illuminate\Support\Carbon|null $dtDeactivated
 * @property int|null $personDeactivated
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript byDaysPastDue($days)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript byReceiver($receiver)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript bySegment($segment)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript bySource($source)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript dslp()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript forDaysPastDue($daysPastDue)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript normal()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript pastDue()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript preDue()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript rpc()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript tpc()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereDaysPastDueFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereDaysPastDueTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereDtDeactivated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript wherePersonDeactivated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereReceiver($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereScriptActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereScriptContentBur($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereScriptContentEng($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereScriptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereScriptRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcScript withoutTrashed()
 */
	class TblCcScript extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $uploadImageId
 * @property string|null $fileName
 * @property string|null $fileType
 * @property string|null $localUrl
 * @property string|null $googleUrl
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property int|null $createdBy
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property int|null $updatedBy
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @property string|null $googleUploadServiceLogId
 * @property int|null $phoneCollectionDetailId
 * @property-read \App\Models\TblCcPhoneCollectionDetail|null $phoneCollectionDetail
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereFileType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereGoogleUploadServiceLogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereGoogleUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereLocalUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage wherePhoneCollectionDetailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage whereUploadImageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TblCcUploadImage withoutTrashed()
 */
	class TblCcUploadImage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $authUserId
 * @property string $email
 * @property string|null $username
 * @property string|null $userFullName
 * @property bool $isActive
 * @property \Illuminate\Support\Carbon|null $lastLoginAt
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property \Illuminate\Support\Carbon|null $deletedAt
 * @property int|null $createdBy
 * @property int|null $updatedBy
 * @property int|null $deletedBy
 * @property string|null $deletedReason
 * @property string|null $extensionNo
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAuthUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereExtensionNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 */
	class User extends \Eloquent {}
}

