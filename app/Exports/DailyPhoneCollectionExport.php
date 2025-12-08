<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Carbon\Carbon;

class DailyPhoneCollectionExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    protected $date;
    protected $remarksCache = [];
    protected $reasonsCache = [];

    public function __construct($date)
    {
        $this->date = $date;
        $this->preloadRemarksAndReasons();
    }

    protected function preloadRemarksAndReasons()
    {
        // Preload all remarks
        $remarks = DB::table('tbl_CcPhoneCollectionDetail as pcd')
            ->join('tbl_CcPhoneCollection as pc', 'pc.phoneCollectionId', '=', 'pcd.phoneCollectionId')
            ->whereNull('pcd.deletedAt')
            ->whereNotNull('pcd.remark')
            ->where('pcd.remark', '!=', '')
            ->whereNull('pc.deletedAt')
            ->whereNotNull('pc.assignedAt')
            ->whereRaw('DATE(pc."assignedAt" AT TIME ZONE \'Asia/Yangon\') = ?', [$this->date])
            ->select('pcd.phoneCollectionId', 'pcd.remark')
            ->get()
            ->groupBy('phoneCollectionId');

        foreach ($remarks as $phoneCollectionId => $items) {
            $this->remarksCache[$phoneCollectionId] = $items->pluck('remark')->unique()->join('; ');
        }

        // Preload all reasons
        $reasons = DB::table('tbl_CcPhoneCollectionDetail as pcd')
            ->join('tbl_CcPhoneCollection as pc', 'pc.phoneCollectionId', '=', 'pcd.phoneCollectionId')
            ->join('tbl_CcReason as r', 'r.reasonId', '=', 'pcd.reasonId')
            ->whereNull('pcd.deletedAt')
            ->whereNull('pc.deletedAt')
            ->whereNotNull('pc.assignedAt')
            ->whereRaw('DATE(pc."assignedAt" AT TIME ZONE \'Asia/Yangon\') = ?', [$this->date])
            ->select('pcd.phoneCollectionId', 'r.reasonName')
            ->get()
            ->groupBy('phoneCollectionId');

        foreach ($reasons as $phoneCollectionId => $items) {
            $this->reasonsCache[$phoneCollectionId] = $items->pluck('reasonName')->unique()->join(', ');
        }
    }

    public function collection()
    {
        return DB::table('tbl_CcPhoneCollection as pc')
            ->leftJoin('users as u', DB::raw('u."authUserId"::text'), '=', DB::raw('pc."assignedTo"::text'))
            ->leftJoin('tbl_CcPromiseHistory as ph', function($join) {
                $join->on('ph.paymentId', '=', 'pc.paymentId')
                    ->where('ph.isActive', true)
                    ->whereRaw('ph."createdAt" = (
                        SELECT MAX(ph2."createdAt")
                        FROM "tbl_CcPromiseHistory" ph2
                        WHERE ph2."paymentId" = pc."paymentId"
                        AND ph2."isActive" = true
                    )');
            })
            ->whereNull('pc.deletedAt')
            ->whereNotNull('pc.assignedAt')
            ->whereRaw('DATE(pc."assignedAt" AT TIME ZONE \'Asia/Yangon\') = ?', [$this->date])
            ->select([
                'pc.phoneCollectionId',
                'pc.salesAreaName',
                'pc.customerFullName',
                'pc.contractNo',
                'pc.contractDate',
                'pc.contractingProductType',
                'pc.paymentNo',
                'pc.segmentType',
                'pc.dueDate',
                'pc.daysSinceLastPayment',
                'ph.promisedPaymentDate',
                'pc.amountUnpaid',
                'u.userFullName as assignedToName',
                DB::raw('pc."assignedAt" AT TIME ZONE \'Asia/Yangon\' as "assignedAt"'),
                'pc.phoneNo1',
                'pc.phoneNo2',
                'pc.phoneNo3',
                'pc.riskType'
            ])
            ->orderBy('pc.assignedAt')
            ->get();
    }

    public function headings(): array
    {
        return [
            'SALES AREA',
            'Customer',
            'CONTRACT NO',
            'CONTRACT DATE',
            'Product Type',
            'Owner Book Status',
            'Owner Book Expiry Date',
            'PAYMENT NO',
            'COLLECTION STATUS',
            'DUE DATE',
            'Days Since Last Payment',
            'P2P',
            'AMOUNT UNPAID',
            'ASSIGNED TO',
            'DT ASSIGNED',
            'PHONE 1',
            'PHONE 2',
            'PHONE 3',
            'REMARK',
            'NOT PAYING REASONS',
            'Source',
            'RiskType'
        ];
    }

    public function map($row): array
    {
        $remarks = $this->remarksCache[$row->phoneCollectionId] ?? '';
        $reasons = $this->reasonsCache[$row->phoneCollectionId] ?? '';

        $collectionStatus = match($row->segmentType) {
            'pre-due' => 'Pre-due',
            'past-due' => 'Past-due',
            'dslp' => 'DSLP',
            default => $row->segmentType
        };

        $source = $row->segmentType === 'dslp' ? 'dslp' : 'phone-collection';

        return [
            $row->salesAreaName,
            $row->customerFullName,
            $row->contractNo,
            $row->contractDate,
            $row->contractingProductType,
            null,
            null,
            $row->paymentNo,
            $collectionStatus,
            $row->dueDate,
            $row->daysSinceLastPayment,
            $row->promisedPaymentDate,
            $row->amountUnpaid,
            $row->assignedToName,
            $row->assignedAt,
            $row->phoneNo1,
            $row->phoneNo2,
            $row->phoneNo3,
            $remarks,
            $reasons,
            $source,
            $row->riskType
        ];
    }

    public function title(): string
    {
        return 'Phone Collection ' . $this->date;
    }
}
