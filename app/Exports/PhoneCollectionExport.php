<?php

namespace App\Exports;

use App\Models\TblCcPhoneCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhoneCollectionExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $fromDate;
    protected $toDate;
    protected $users;
    protected $latestAttempts;
    protected $caseResults;
    protected $reasons;
    protected $postponeCountByContract;

    public function __construct($fromDate, $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    /**
     * Load all data
     */
    public function collection()
    {
        Log::info('Starting Excel export', [
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate
        ]);

        // Query phone collections (same as your current code)
        $phoneCollections = TblCcPhoneCollection::select([
                'tbl_CcPhoneCollection.*',
                'latest_promise.promisedPaymentDate as latest_promisedPaymentDate',
                'latest_promise.dtCallLater as latest_dtCallLater'
            ])
            ->with(['batch'])
            ->leftJoin('tbl_CcPromiseHistory as latest_promise', function($join) {
                $join->on('latest_promise.paymentId', '=', 'tbl_CcPhoneCollection.paymentId')
                    ->where('latest_promise.isActive', '=', true)
                    ->whereRaw('"latest_promise"."createdAt" = (
                        SELECT MAX("ph2"."createdAt")
                        FROM "tbl_CcPromiseHistory" "ph2"
                        WHERE "ph2"."paymentId" = "tbl_CcPhoneCollection"."paymentId"
                        AND "ph2"."isActive" = true
                    )');
            })
            ->whereRaw(
                'DATE("tbl_CcPhoneCollection"."assignedAt" AT TIME ZONE \'Asia/Yangon\') BETWEEN ? AND ?',
                [$this->fromDate, $this->toDate]
            )
            ->orderBy('tbl_CcPhoneCollection.assignedAt', 'asc')
            ->get();

        if ($phoneCollections->isEmpty()) {
            return collect([]);
        }

        // Batch load users
        $userIds = collect();
        $phoneCollections->each(function($pc) use ($userIds) {
            if ($pc->assignedBy) $userIds->push($pc->assignedBy);
            if ($pc->assignedTo) $userIds->push($pc->assignedTo);
            if ($pc->lastAttemptBy) $userIds->push($pc->lastAttemptBy);
        });
        $userIds = $userIds->unique()->filter()->values()->toArray();

        $this->users = [];
        if (!empty($userIds)) {
            $this->users = User::whereIn('authUserId', $userIds)
                ->get()
                ->keyBy('authUserId');
        }

        // Batch load latest attempts
        $phoneCollectionIds = $phoneCollections->pluck('phoneCollectionId')->toArray();

        $this->latestAttempts = DB::table('tbl_CcPhoneCollectionDetail as pcd1')
            ->select('pcd1.*')
            ->whereIn('pcd1.phoneCollectionId', $phoneCollectionIds)
            ->whereRaw('"pcd1"."phoneCollectionDetailId" = (
                SELECT "pcd2"."phoneCollectionDetailId"
                FROM "tbl_CcPhoneCollectionDetail" "pcd2"
                WHERE "pcd2"."phoneCollectionId" = "pcd1"."phoneCollectionId"
                ORDER BY "pcd2"."dtCallStarted" DESC
                LIMIT 1
            )')
            ->get()
            ->keyBy('phoneCollectionId');

        // Batch load case results
        $caseResultIds = $this->latestAttempts->pluck('callResultId')->filter()->unique()->values()->toArray();
        $this->caseResults = [];
        if (!empty($caseResultIds)) {
            $this->caseResults = DB::table('tbl_CcCaseResult')
                ->whereIn('caseResultId', $caseResultIds)
                ->get()
                ->keyBy('caseResultId');
        }

        // Batch load reasons
        $reasonIds = $this->latestAttempts->pluck('reasonId')->filter()->unique()->values()->toArray();
        $this->reasons = [];
        if (!empty($reasonIds)) {
            $this->reasons = DB::table('tbl_CcReason')
                ->whereIn('reasonId', $reasonIds)
                ->get()
                ->keyBy('reasonId');
        }

        // Count postpone payments
        $contractIds = $phoneCollections->pluck('contractId')->filter()->unique()->toArray();
        $this->postponeCountByContract = [];

        if (!empty($contractIds)) {
            $postponeCounts = DB::table('tbl_CcPhoneCollectionDetail as pcd')
                ->join('tbl_CcPhoneCollection as pc', 'pc.phoneCollectionId', '=', 'pcd.phoneCollectionId')
                ->whereIn('pc.contractId', $contractIds)
                ->where('pcd.askingPostponePayment', '=', true)
                ->groupBy('pc.contractId')
                ->select([
                    'pc.contractId',
                    DB::raw('COUNT(*) as postpone_count')
                ])
                ->get();

            foreach ($postponeCounts as $count) {
                $this->postponeCountByContract[$count->contractId] = (int)$count->postpone_count;
            }
        }

        Log::info('Excel export data loaded', [
            'total_records' => $phoneCollections->count()
        ]);

        return $phoneCollections;
    }

    /**
     * Define Excel headers
     */
    public function headings(): array
    {
        return [
            'Sales Area',
            'Branch',
            'Customer ID',
            'Birth Date',
            'Gender',
            'Contract No',
            'Contract Date',
            'Product Type',
            'Product Name',
            'Product Color',
            'Plate No',
            'Unit Price',
            'Payment No',
            'Payment Status',
            'Last Payment Date',
            'Due Date',
            'Payment Amount',
            'Penalty Amount',
            'Penalty Exempted',
            'Total Amount',
            'Amount Paid',
            'Amount Unpaid',
            'Batch Name',           // ← THÊM
            'DPD',                  // ← THÊM
            'Assigned By',
            'Assigned To',
            // 'Assigned At',       // ← XÓA DÒNG NÀY
            'Last Attempt By',
            'Call Started',
            'Call Ended',
            'Duration (seconds)',
            'Call Status',
            'Call Result',
            'Not Paying Reason',
            'Standard Remark',
            'Detailed Remark',      // ← THÊM
            'Uncall',
            'Reschedule',
            'Phone No 1',
            'Phone No 2',
            'Phone No 3',
            'Home Address',
            'No Of Penalty Fees Charged',
            'No Of Penalty Fees Exempted',
            'No Of Penalty Fees Paid',
            'Total Penalty Amount Charged',
            'Promised Payment Date',
            'Call Later Date',
            'No Of Asking Postpone Payment',
        ];
    }

    /**
     * Map each row
     */
    public function map($pc): array
    {
        // Get user names
        $assignedByName = null;
        if ($pc->assignedBy) {
            if ($pc->assignedBy === 1) {
                $assignedByName = 'System';
            } elseif (isset($this->users[$pc->assignedBy])) {
                $assignedByName = $this->users[$pc->assignedBy]->userFullName;
            }
        }

        $assignedToName = isset($this->users[$pc->assignedTo])
            ? $this->users[$pc->assignedTo]->userFullName
            : null;

        $lastAttemptByName = isset($this->users[$pc->lastAttemptBy])
            ? $this->users[$pc->lastAttemptBy]->userFullName
            : null;

        // Get latest attempt details
        $latestAttempt = $this->latestAttempts->get($pc->phoneCollectionId);

        $dtCallStarted = null;
        $dtCallEnded = null;
        $duration = null;
        $callStatus = null;
        $callResultName = null;
        $standardRemarkContent = null;
        $detailedRemark = null;
        $notPayingReason = null;

        if ($latestAttempt) {
            // Convert to Myanmar timezone
            $dtCallStarted = $latestAttempt->dtCallStarted
                ? \Carbon\Carbon::parse($latestAttempt->dtCallStarted)->timezone('Asia/Yangon')->format('Y-m-d H:i:s')
                : null;
            $dtCallEnded = $latestAttempt->dtCallEnded
                ? \Carbon\Carbon::parse($latestAttempt->dtCallEnded)->timezone('Asia/Yangon')->format('Y-m-d H:i:s')
                : null;

            $callStatus = $latestAttempt->callStatus;
            $standardRemarkContent = $latestAttempt->standardRemarkContent;
            $detailedRemark = $latestAttempt->remark;

            // Calculate duration correctly
            if ($latestAttempt->dtCallStarted && $latestAttempt->dtCallEnded) {
                try {
                    $start = \Carbon\Carbon::parse($latestAttempt->dtCallStarted);
                    $end = \Carbon\Carbon::parse($latestAttempt->dtCallEnded);
                    $duration = $start->diffInSeconds($end);
                } catch (\Exception $e) {
                    $duration = null;
                }
            }

            // Get case result
            if ($latestAttempt->callResultId && isset($this->caseResults[$latestAttempt->callResultId])) {
                $callResultName = $this->caseResults[$latestAttempt->callResultId]->caseResultName;
            }

            // Get reason
            if ($latestAttempt->reasonId && isset($this->reasons[$latestAttempt->reasonId])) {
                $notPayingReason = $this->reasons[$latestAttempt->reasonId]->reasonName;
            }
        }

        // Get batch name from relationship
        $batchName = $pc->batch?->batchName ?? null;

        // Get DPD from daysOverdueNet (already calculated in DB)
        $dpd = $pc->daysOverdueNet ?? 0;

        return [
            $pc->salesAreaName,
            $pc->contractPlaceName,
            $pc->customerId,
            $pc->birthDate?->format('Y-m-d'),
            $pc->gender,
            $pc->contractNo,
            $pc->contractDate?->format('Y-m-d'),
            $pc->contractingProductType,
            $pc->productName,
            $pc->productColor,
            $pc->plateNo,
            $pc->unitPrice,
            $pc->paymentNo,
            $pc->paymentStatus,
            $pc->lastPaymentDate?->format('Y-m-d'),
            $pc->dueDate?->format('Y-m-d'),
            $pc->paymentAmount,
            $pc->penaltyAmount,
            $pc->penaltyExempted,
            $pc->totalAmount,
            $pc->amountPaid,
            $pc->amountUnpaid,
            $batchName,                    // ← SỬA: lấy từ relationship
            $dpd,                          // ← SỬA: lấy từ daysOverdueNet
            $assignedByName,
            $assignedToName,
            $lastAttemptByName,
            $dtCallStarted,                // ← ĐÃ CONVERT SANG YANGON TIMEZONE
            $dtCallEnded,                  // ← ĐÃ CONVERT SANG YANGON TIMEZONE
            $duration,
            $callStatus,
            $callResultName,
            $notPayingReason,
            $standardRemarkContent,
            $detailedRemark,
            $pc->lastAttemptAt === null ? 'Yes' : 'No',
            $pc->reschedule ? 'Yes' : 'No',
            $pc->phoneNo1,
            $pc->phoneNo2,
            $pc->phoneNo3,
            $pc->homeAddress,
            $pc->noOfPenaltyFeesCharged,
            $pc->noOfPenaltyFeesExempted,
            $pc->noOfPenaltyFeesPaid,
            $pc->totalPenaltyAmountCharged,
            $pc->latest_promisedPaymentDate ? \Carbon\Carbon::parse($pc->latest_promisedPaymentDate)->format('Y-m-d') : null,
            $pc->latest_dtCallLater ? \Carbon\Carbon::parse($pc->latest_dtCallLater)->timezone('Asia/Yangon')->format('Y-m-d H:i:s') : null,  // ← YANGON TIMEZONE
            $this->postponeCountByContract[$pc->contractId] ?? 0,
        ];
    }

    /**
     * Style the Excel sheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]], // Make header bold
        ];
    }
}
