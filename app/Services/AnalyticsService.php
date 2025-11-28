<?php

namespace App\Services;

use App\Models\TblCcPhoneCollection;
use App\Models\TblCcPhoneCollectionDetail;
use App\Models\TblCcCaseResult;
use App\Models\TblCcReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get analytics summary for dashboard
     *
     * @param string $from Date in Y-m-d format
     * @param string $to Date in Y-m-d format
     * @return array
     */
    public function getAnalyticsSummary(string $from, string $to): array
    {
        Log::info('Getting analytics summary', [
            'from' => $from,
            'to' => $to
        ]);

        // Convert to datetime range
        $fromDateTime = Carbon::parse($from)->startOfDay();
        $toDateTime = Carbon::parse($to)->endOfDay();

        return [
            'contactRate' => $this->getContactRate($fromDateTime, $toDateTime),
            'callResults' => $this->getCallResults($fromDateTime, $toDateTime),
            'agentStats' => $this->getAgentStats($fromDateTime, $toDateTime),
            'collectionTrend' => $this->getCollectionTrend($fromDateTime, $toDateTime),
            'dpdDistribution' => $this->getDpdDistribution($fromDateTime, $toDateTime),
            'penaltyAnalysis' => $this->getPenaltyAnalysis($fromDateTime, $toDateTime),
            'postponementStats' => $this->getPostponementStats($fromDateTime, $toDateTime),
            'notPayingReasons' => $this->getNotPayingReasons($fromDateTime, $toDateTime),
            'rescheduleStats' => $this->getRescheduleStats($fromDateTime, $toDateTime),
        ];
    }

    /**
     * Get contact rate statistics
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getContactRate($from, $to): array
    {
        // Get reached and not reached from phone collection details
        $contactStats = DB::table('tbl_CcPhoneCollectionDetail')
            ->whereBetween(DB::raw('"createdAt"'), [$from, $to])
            ->selectRaw('
                COUNT(CASE WHEN "callStatus" = \'reached\' THEN 1 END) as reached,
                COUNT(CASE WHEN "callStatus" IN (\'ring\', \'busy\', \'cancelled\', \'power_off\', \'wrong_number\', \'no_contact\') THEN 1 END) as "notReached"
            ')
            ->first();

        // Get uncalled (assigned but never attempted)
        $uncalled = DB::table('tbl_CcPhoneCollection')
            ->whereBetween(DB::raw('"assignedAt"'), [$from, $to])
            ->where(DB::raw('"totalAttempts"'), 0)
            ->count();

        return [
            'reached' => (int) ($contactStats->reached ?? 0),
            'notReached' => (int) ($contactStats->notReached ?? 0),
            'uncalled' => $uncalled,
        ];
    }

    /**
     * Get call results breakdown
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getCallResults($from, $to): array
    {
        $results = DB::table('tbl_CcPhoneCollectionDetail')
            ->join(
                'tbl_CcCaseResult',
                'tbl_CcPhoneCollectionDetail.callResultId',
                '=',
                'tbl_CcCaseResult.caseResultId'
            )
            ->whereBetween(DB::raw('"tbl_CcPhoneCollectionDetail"."createdAt"'), [$from, $to])
            ->whereNotNull(DB::raw('"tbl_CcPhoneCollectionDetail"."callResultId"'))
            ->groupBy(DB::raw('"tbl_CcCaseResult"."caseResultName"'))
            ->selectRaw('"tbl_CcCaseResult"."caseResultName" as name, COUNT(*) as count')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'count' => (int) $item->count
                ];
            });

        return $results->toArray();
    }

    /**
     * Get agent performance statistics
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getAgentStats($from, $to): array
    {
        $stats = DB::table('tbl_CcPhoneCollectionDetail')
            ->join(
                'tbl_CcPhoneCollection',
                'tbl_CcPhoneCollectionDetail.phoneCollectionId',
                '=',
                'tbl_CcPhoneCollection.phoneCollectionId'
            )
            ->join(
                'users',
                DB::raw('CAST("tbl_CcPhoneCollection"."assignedTo" AS VARCHAR)'),
                '=',
                'users.authUserId'
            )
            ->whereBetween(DB::raw('"tbl_CcPhoneCollectionDetail"."createdAt"'), [$from, $to])
            ->groupBy(DB::raw('users."authUserId"'), DB::raw('users."userFullName"'))
            ->selectRaw('
                users."userFullName" as "agentName",
                COUNT(DISTINCT "tbl_CcPhoneCollectionDetail"."phoneCollectionDetailId") as "totalCalls",
                SUM(CASE WHEN "tbl_CcPhoneCollectionDetail"."callStatus" = \'reached\' THEN 1 ELSE 0 END) as "reachedCalls",
                COALESCE(SUM(CASE WHEN "tbl_CcPhoneCollectionDetail"."createdAt" = (
                    SELECT MAX("createdAt")
                    FROM "tbl_CcPhoneCollectionDetail" pcd2
                    WHERE pcd2."phoneCollectionId" = "tbl_CcPhoneCollection"."phoneCollectionId"
                ) THEN "tbl_CcPhoneCollection"."amountPaid" ELSE 0 END), 0) as "totalAmountCollected"
            ')
            ->orderByDesc('totalCalls')
            ->get()
            ->map(function ($item) {
                return [
                    'agentName' => $item->agentName,
                    'totalCalls' => (int) $item->totalCalls,
                    'reachedCalls' => (int) $item->reachedCalls,
                    'totalAmountCollected' => (int) $item->totalAmountCollected,
                ];
            });

        return $stats->toArray();
    }

    /**
     * Get collection trend over time
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getCollectionTrend($from, $to): array
    {
        $trend = DB::table('tbl_CcPhoneCollection')
            ->whereBetween(DB::raw('"assignedAt"'), [$from, $to])
            ->groupBy(DB::raw('DATE("assignedAt")'))
            ->selectRaw('
                DATE("assignedAt") as date,
                COALESCE(SUM("amountPaid"), 0) as "amountPaid",
                COALESCE(SUM("amountUnpaid"), 0) as "amountUnpaid"
            ')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'amountPaid' => (int) $item->amountPaid,
                    'amountUnpaid' => (int) $item->amountUnpaid,
                ];
            });

        return $trend->toArray();
    }

    /**
     * Get DPD distribution
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getDpdDistribution($from, $to): array
    {
        $distribution = DB::table('tbl_CcPhoneCollection')
            ->whereBetween(DB::raw('"assignedAt"'), [$from, $to])
            ->selectRaw('
                CASE
                    WHEN "daysOverdueGross" BETWEEN 0 AND 7 THEN \'0-7\'
                    WHEN "daysOverdueGross" BETWEEN 8 AND 15 THEN \'8-15\'
                    WHEN "daysOverdueGross" BETWEEN 16 AND 30 THEN \'16-30\'
                    ELSE \'31+\'
                END as range,
                COUNT(*) as count
            ')
            ->groupBy(DB::raw('
                CASE
                    WHEN "daysOverdueGross" BETWEEN 0 AND 7 THEN \'0-7\'
                    WHEN "daysOverdueGross" BETWEEN 8 AND 15 THEN \'8-15\'
                    WHEN "daysOverdueGross" BETWEEN 16 AND 30 THEN \'16-30\'
                    ELSE \'31+\'
                END
            '))
            ->orderBy(DB::raw("
                MIN(CASE
                    WHEN \"daysOverdueGross\" BETWEEN 0 AND 7 THEN 1
                    WHEN \"daysOverdueGross\" BETWEEN 8 AND 15 THEN 2
                    WHEN \"daysOverdueGross\" BETWEEN 16 AND 30 THEN 3
                    ELSE 4
                END)
            "))
            ->get()
            ->map(function ($item) {
                return [
                    'range' => $item->range,
                    'count' => (int) $item->count
                ];
            });

        return $distribution->toArray();
    }

    /**
     * Get penalty fee analysis
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getPenaltyAnalysis($from, $to): array
    {
        $analysis = DB::table('tbl_CcPhoneCollection')
            ->whereBetween(DB::raw('"assignedAt"'), [$from, $to])
            ->selectRaw('
                COALESCE(SUM(
                    CAST("totalPenaltyAmountCharged" AS NUMERIC) *
                    COALESCE(CAST("noOfPenaltyFeesCharged" AS NUMERIC), 0)
                ), 0) as "totalCharged",
                COALESCE(SUM(
                    CAST("totalPenaltyAmountCharged" AS NUMERIC) *
                    COALESCE(CAST("noOfPenaltyFeesExempted" AS NUMERIC), 0)
                ), 0) as "totalExempted",
                COALESCE(SUM(
                    CAST("totalPenaltyAmountCharged" AS NUMERIC) *
                    COALESCE(CAST("noOfPenaltyFeesPaid" AS NUMERIC), 0)
                ), 0) as "totalPaid"
            ')
            ->first();

        return [
            'totalCharged' => (int) ($analysis->totalCharged ?? 0),
            'totalExempted' => (int) ($analysis->totalExempted ?? 0),
            'totalPaid' => (int) ($analysis->totalPaid ?? 0),
        ];
    }

    /**
     * Get postponement statistics
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getPostponementStats($from, $to): array
    {
        // Get total cases and average requests
        $stats = DB::table(DB::raw('(
            SELECT
                "phoneCollectionId",
                COUNT(*) as request_count
            FROM "tbl_CcPhoneCollectionDetail"
            WHERE "createdAt" BETWEEN ? AND ?
            AND "askingPostponePayment" = true
            GROUP BY "phoneCollectionId"
        ) as sub'))
            ->setBindings([$from, $to])
            ->selectRaw('
                COUNT(*) as "totalCases",
                COALESCE(AVG(request_count), 0) as "averageRequests"
            ')
            ->first();

        return [
            'totalCases' => (int) ($stats->totalCases ?? 0),
            'averageRequests' => round((float) ($stats->averageRequests ?? 0), 2),
        ];
    }

    /**
     * Get reasons why customers are not paying
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getNotPayingReasons($from, $to): array
    {
        $reasons = DB::table('tbl_CcPhoneCollectionDetail')
            ->join(
                'tbl_CcReason',
                'tbl_CcPhoneCollectionDetail.reasonId',
                '=',
                'tbl_CcReason.reasonId'
            )
            ->whereBetween(DB::raw('"tbl_CcPhoneCollectionDetail"."createdAt"'), [$from, $to])
            ->whereNotNull(DB::raw('"tbl_CcPhoneCollectionDetail"."reasonId"'))
            ->groupBy(DB::raw('"tbl_CcReason"."reasonName"'))
            ->selectRaw('"tbl_CcReason"."reasonName" as reason, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(10) // Top 10 reasons
            ->get()
            ->map(function ($item) {
                return [
                    'reason' => $item->reason,
                    'count' => (int) $item->count
                ];
            });

        return $reasons->toArray();
    }

    /**
     * Get reschedule statistics
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function getRescheduleStats($from, $to): array
    {
        $stats = DB::table('tbl_CcPhoneCollectionDetail')
            ->whereBetween(DB::raw('"createdAt"'), [$from, $to])
            ->selectRaw('
                COUNT(CASE WHEN "reschedulingEvidence" = true THEN 1 END) as rescheduled,
                COUNT(CASE WHEN "reschedulingEvidence" = false OR "reschedulingEvidence" IS NULL THEN 1 END) as immediate
            ')
            ->first();

        return [
            'rescheduled' => (int) ($stats->rescheduled ?? 0),
            'immediate' => (int) ($stats->immediate ?? 0),
        ];
    }
}
