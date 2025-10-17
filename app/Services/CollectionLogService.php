<?php

namespace App\Services;

use App\Models\TblCcPhoneCollectionDetail;
use App\Models\TblCcPhoneCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Exception;

class CollectionLogService
{
    /**
     * Get unified collection logs from both phone collection and litigation
     *
     * @param int $contractId
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    public function getUnifiedLogs(int $contractId, string $fromDate, string $toDate): array
    {
        Log::info('START getUnifiedLogs', [
            'contract_id' => $contractId,
            'from' => $fromDate,
            'to' => $toDate,
        ]);

        // Step 1: Get phone collection details
        $phoneCollectionLogs = $this->getPhoneCollectionLogs($contractId, $fromDate, $toDate);

        // Step 2: Get litigation journals
        $litigationLogs = $this->getLitigationJournals($contractId, $fromDate, $toDate);

        // ✅ Step 2.5: Enrich litigation with contract info from phone collection
        if ($phoneCollectionLogs->isNotEmpty() && $litigationLogs->isNotEmpty()) {
            // Get contract info from first phone collection log
            $contractInfo = [
                'contractNo' => $phoneCollectionLogs->first()['contractNo'] ?? null,
                'customerFullName' => $phoneCollectionLogs->first()['customerFullName'] ?? null,
            ];

            // Enrich each litigation log
            $litigationLogs = $litigationLogs->map(function ($log) use ($contractInfo) {
                $log['contractNo'] = $contractInfo['contractNo'];
                $log['customerFullName'] = $contractInfo['customerFullName'];
                return $log;
            });

            Log::info('Litigation logs enriched with contract info', [
                'contract_no' => $contractInfo['contractNo'],
                'customer_name' => $contractInfo['customerFullName'],
            ]);
        }

        // Step 3: Merge and sort
        $unifiedLogs = $phoneCollectionLogs->merge($litigationLogs)
            ->sortByDesc('timestamp')
            ->values();

        Log::info('Collection logs merged and sorted', [
            'total' => $unifiedLogs->count(),
            'phone_count' => $phoneCollectionLogs->count(),
            'litigation_count' => $litigationLogs->count(),
        ]);

        return [
            'logs' => $unifiedLogs,
            'total' => $unifiedLogs->count(),
            'phoneCollectionCount' => $phoneCollectionLogs->count(),
            'litigationCount' => $litigationLogs->count(),
            'dateRange' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
        ];
    }

    /**
     * Get phone collection logs from database
     *
     * @param int $contractId
     * @param string $fromDate
     * @param string $toDate
     * @return Collection
     */
    protected function getPhoneCollectionLogs(int $contractId, string $fromDate, string $toDate): Collection
    {
        try {
            // Get phone collection IDs for this contract
            $phoneCollectionIds = TblCcPhoneCollection::where('contractId', $contractId)
                ->pluck('phoneCollectionId')
                ->toArray();

            if (empty($phoneCollectionIds)) {
                Log::info('No phone collections found for contract', ['contract_id' => $contractId]);
                return collect();
            }

            // ✅ Eager load creator relationship
            $details = TblCcPhoneCollectionDetail::with([
                'creator', // ✅ Load user info
                'standardRemark',
                'callResult',
                'phoneCollection',
                'uploadImages'
            ])
                ->whereIn('phoneCollectionId', $phoneCollectionIds)
                ->whereBetween('createdAt', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->orderBy('createdAt', 'desc')
                ->get();

            Log::info('Phone collection logs retrieved', [
                'contract_id' => $contractId,
                'count' => $details->count(),
            ]);

            // Transform to unified format
            return $details->map(function ($detail) {
                return $this->transformPhoneCollectionDetail($detail);
            });

        } catch (Exception $e) {
            Log::error('Failed to get phone collection logs', [
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get litigation journals from Maximus API
     *
     * @param int $contractId
     * @param string $fromDate
     * @param string $toDate
     * @return Collection
     */
    protected function getLitigationJournals(int $contractId, string $fromDate, string $toDate): Collection
    {
        try {
            $url = "https://maximus.vnapp.xyz/api/v1/cc/contracts/{$contractId}/litigation-journals/from/{$fromDate}/to/{$toDate}";

            Log::info('Calling Maximus API for litigation journals', [
                'url' => $url,
                'contract_id' => $contractId,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => 't03JN3y8L12gzVbuLuorjwBAHgVAkkY6QOvJkP6m',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::error('Maximus API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return collect();
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                Log::error('Maximus API returned error status', ['data' => $data]);
                return collect();
            }

            $journals = $data['data'] ?? [];

            // Manual date filter
            $journals = collect($journals)->filter(function($journal) use ($fromDate, $toDate) {
                $dtCreated = $journal['dtCreated'] ?? null;

                if (!$dtCreated) {
                    return false;
                }

                $createdDate = \Carbon\Carbon::parse($dtCreated);
                $from = \Carbon\Carbon::parse($fromDate)->startOfDay();
                $to = \Carbon\Carbon::parse($toDate)->endOfDay();

                return $createdDate->between($from, $to);
            })->values();

            Log::info('Litigation journals after date filter', [
                'total_from_api' => count($data['data'] ?? []),
                'after_filter' => $journals->count(),
            ]);

            // ✅ ADD TRY-CATCH FOR TRANSFORMATION
            $transformed = collect();
            foreach ($journals as $journal) {
                try {
                    $transformedJournal = $this->transformLitigationJournal($journal);
                    $transformed->push($transformedJournal);

                    Log::info('✅ Litigation journal transformed', [
                        'journal_id' => $journal['journalId'] ?? 'unknown',
                    ]);

                } catch (Exception $e) {
                    Log::error('❌ Failed to transform litigation journal', [
                        'journal_id' => $journal['journalId'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            Log::info('Litigation transformation complete', [
                'input_count' => $journals->count(),
                'output_count' => $transformed->count(),
            ]);

            return $transformed;

        } catch (Exception $e) {
            Log::error('Failed to get litigation journals', [
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return collect();
        }
    }

    /**
     * Transform phone collection detail to unified format
     * ✅ INCLUDE ALL FIELDS
     *
     * @param TblCcPhoneCollectionDetail $detail
     * @return array
     */
    protected function transformPhoneCollectionDetail(TblCcPhoneCollectionDetail $detail): array
    {
        return [
            // ========================================
            // COMMON FIELDS
            // ========================================
            'logId' => "phone_collection_{$detail->phoneCollectionDetailId}",
            'logType' => 'phone_collection',
            'timestamp' => $detail->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
            'contractId' => $detail->phoneCollection?->contractId,
            'contractNo' => $detail->phoneCollection?->contractNo,
            'customerFullName' => $detail->phoneCollection?->customerFullName,
            'remark' => $detail->remark,
            'promisedPaymentDate' => $detail->promisedPaymentDate
                ? $detail->promisedPaymentDate->utc()->format('Y-m-d\TH:i:s\Z')
                : null,

            // ✅ UNIFIED CREATOR (from users table via authUserId)
            'creator' => [
                'userId' => $detail->creator?->authUserId ?? $detail->createdBy,
                'username' => $detail->creator?->username ?? null,
                'userFullName' => $detail->creator?->userFullName ?? null,
                'userRemark' => null, // Not available in users table
                'salesAreaId' => null, // Not available in users table
                'workPlaceId' => null, // Not available in users table
            ],

            'images' => $detail->uploadImages->map(function ($image) {
                return [
                    'imageId' => $image->uploadImageId,
                    'imageType' => 'phone_collection_image',
                    'fileName' => $image->fileName,
                    'fileType' => $image->fileType,
                    'localUrl' => $image->localUrl,
                    'googleUrl' => $image->googleUrl,
                    'createdAt' => $image->createdAt?->format('Y-m-d H:i:s'),
                ];
            })->toArray(),

            // ========================================
            // LITIGATION-SPECIFIC FIELDS (null for phone collection)
            // ========================================
            'journalId' => null,
            'delinquencyReasonType' => null,
            'voucherNo' => null,
            'voucherAmountReceived' => null,
            'voucherIsFullPayment' => null,
            'voucherAmountReconciled' => null,
            'journalRemark' => null,
            'dtCreated' => null,

            // ========================================
            // PHONE COLLECTION-SPECIFIC FIELDS
            // ========================================
            'phoneCollectionDetailId' => $detail->phoneCollectionDetailId,
            'phoneCollectionId' => $detail->phoneCollectionId,
            'contactType' => $detail->contactType,
            'phoneId' => $detail->phoneId,
            'contactDetailId' => $detail->contactDetailId,
            'contactPhoneNumber' => $detail->contactPhoneNumer,
            'contactName' => $detail->contactName,
            'contactRelation' => $detail->contactRelation,
            'callStatus' => $detail->callStatus,
            'callResultId' => $detail->callResultId,
            'callResult' => $detail->callResult ? [
                'caseResultId' => $detail->callResult->caseResultId,
                'caseResultName' => $detail->callResult->caseResultName,
                'caseResultRemark' => $detail->callResult->caseResultRemark,
                'contactType' => $detail->callResult->contactType,
                'batchId' => $detail->callResult->batchId,
                'requiredField' => $detail->callResult->requiredField,
            ] : null,
            'leaveMessage' => $detail->leaveMessage,
            'standardRemarkId' => $detail->standardRemarkId,
            'standardRemarkContent' => $detail->standardRemarkContent,
            'standardRemark' => $detail->standardRemark ? [
                'remarkId' => $detail->standardRemark->remarkId,
                'remarkContent' => $detail->standardRemark->remarkContent,
                'remarkActive' => $detail->standardRemark->remarkActive,
                'contactType' => $detail->standardRemark->contactType,
                'batchId' => $detail->standardRemark->batchId,
            ] : null,
            'askingPostponePayment' => $detail->askingPostponePayment,
            'dtCallLater' => $detail->dtCallLater?->utc()->format('Y-m-d\TH:i:s\Z'),
            'dtCallStarted' => $detail->dtCallStarted?->utc()->format('Y-m-d\TH:i:s\Z'),
            'dtCallEnded' => $detail->dtCallEnded?->utc()->format('Y-m-d\TH:i:s\Z'),
            'callDuration' => $detail->dtCallStarted && $detail->dtCallEnded
                ? $detail->dtCallStarted->diffInSeconds($detail->dtCallEnded)
                : null,
            'updatePhoneRequest' => $detail->updatePhoneRequest,
            'updatePhoneRemark' => $detail->updatePhoneRemark,
            'reschedulingEvidence' => $detail->reschedulingEvidence,

            // Audit fields
            'createdAt' => $detail->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
            'createdBy' => $detail->createdBy,
            'updatedAt' => $detail->updatedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updatedBy' => $detail->updatedBy,
            'deletedAt' => $detail->deletedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
            'deletedBy' => $detail->deletedBy,
            'deletedReason' => $detail->deletedReason,

            // Phone Collection info
            'phoneCollection' => [
                'phoneCollectionId' => $detail->phoneCollection?->phoneCollectionId,
                'contractId' => $detail->phoneCollection?->contractId,
                'contractNo' => $detail->phoneCollection?->contractNo,
                'contractDate' => $detail->phoneCollection?->contractDate?->format('Y-m-d'),
                'contractType' => $detail->phoneCollection?->contractType,
                'contractingProductType' => $detail->phoneCollection?->contractingProductType,
                'customerId' => $detail->phoneCollection?->customerId,
                'customerFullName' => $detail->phoneCollection?->customerFullName,
                'gender' => $detail->phoneCollection?->gender,
                'birthDate' => $detail->phoneCollection?->birthDate?->format('Y-m-d'),
                'assetId' => $detail->phoneCollection?->assetId,
                'paymentId' => $detail->phoneCollection?->paymentId,
                'paymentNo' => $detail->phoneCollection?->paymentNo,
                'dueDate' => $detail->phoneCollection?->dueDate?->format('Y-m-d'),
                'daysOverdueGross' => $detail->phoneCollection?->daysOverdueGross,
                'daysOverdueNet' => $detail->phoneCollection?->daysOverdueNet,
                'daysSinceLastPayment' => $detail->phoneCollection?->daysSinceLastPayment,
                'lastPaymentDate' => $detail->phoneCollection?->lastPaymentDate?->format('Y-m-d'),
                'paymentAmount' => $detail->phoneCollection?->paymentAmount,
                'penaltyAmount' => $detail->phoneCollection?->penaltyAmount,
                'totalAmount' => $detail->phoneCollection?->totalAmount,
                'amountPaid' => $detail->phoneCollection?->amountPaid,
                'amountUnpaid' => $detail->phoneCollection?->amountUnpaid,
                'segmentType' => $detail->phoneCollection?->segmentType,
                'batchId' => $detail->phoneCollection?->batchId,
                'riskType' => $detail->phoneCollection?->riskType,
                'status' => $detail->phoneCollection?->status,
                'assignedTo' => $detail->phoneCollection?->assignedTo,
                'assignedBy' => $detail->phoneCollection?->assignedBy,
                'assignedAt' => $detail->phoneCollection?->assignedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                'totalAttempts' => $detail->phoneCollection?->totalAttempts,
                'lastAttemptAt' => $detail->phoneCollection?->lastAttemptAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                'lastAttemptBy' => $detail->phoneCollection?->lastAttemptBy,
            ],
        ];
    }

    /**
     * Transform litigation journal to unified format
     * ✅ INCLUDE ALL FIELDS
     *
     * @param array $journal
     * @return array
     */
    protected function transformLitigationJournal(array $journal): array
    {
        return [
            // ========================================
            // COMMON FIELDS
            // ========================================
            'logId' => "litigation_{$journal['journalId']}",
            'logType' => 'litigation',
            'timestamp' => \Carbon\Carbon::parse($journal['dtCreated'])->utc()->format('Y-m-d\TH:i:s\Z'),
            'contractId' => $journal['contractId'],
            'contractNo' => null, // Will be filled by getUnifiedLogs()
            'customerFullName' => null, // Will be filled by getUnifiedLogs()
            'remark' => $journal['journalRemark'] ?? null,
            'promisedPaymentDate' => $journal['promisedPaymentDate']
                ? \Carbon\Carbon::parse($journal['promisedPaymentDate'])->utc()->format('Y-m-d\TH:i:s\Z')
                : null,

            // ✅ UNIFIED CREATOR (from person_created in litigation API)
            'creator' => [
                'userId' => $journal['person_created']['userId'] ?? null,
                'username' => $journal['person_created']['userName'] ?? null,
                'userFullName' => $journal['person_created']['userFullName'] ?? null,
                'userRemark' => $journal['person_created']['userRemark'] ?? null,
                'salesAreaId' => $journal['person_created']['salesAreaId'] ?? null,
                'workPlaceId' => $journal['person_created']['workPlaceId'] ?? null,
            ],

            'images' => collect($journal['images'] ?? [])->map(function ($image) {
                // ✅ Combine domain + path for full URL
                $localUrl = !empty($image['journalImageDomain']) && !empty($image['journalImagePath'])
                    ? rtrim($image['journalImageDomain'], '/') . '/' . ltrim($image['journalImagePath'], '/')
                    : $image['journalImagePath'];

                return [
                    'imageId' => $image['journalImageId'],
                    'imageType' => $image['journalImageType'],
                    'fileName' => basename($image['journalImagePath']),
                    'fileType' => $image['journalImageExtension'],
                    'localUrl' => $localUrl,  // ✅ Full URL: https://app-be.r2omm.xyz/public/uploads/...
                    'googleUrl' => $image['journalImageGGDrivePath'],
                    'createdAt' => null,
                ];
            })->toArray(),

            // ========================================
            // LITIGATION-SPECIFIC FIELDS
            // ========================================
            'journalId' => $journal['journalId'],
            'delinquencyReasonType' => $journal['delinquencyReasonType'] ?? null,
            'voucherNo' => $journal['voucherNo'] ?? null,
            'voucherAmountReceived' => $journal['voucherAmountReceived'] ?? null,
            'voucherIsFullPayment' => $journal['voucherIsFullPayment'] ?? null,
            'voucherAmountReconciled' => $journal['voucherAmountReconciled'] ?? null,
            'journalRemark' => $journal['journalRemark'] ?? null,
            'dtCreated' => \Carbon\Carbon::parse($journal['dtCreated'])->utc()->format('Y-m-d\TH:i:s\Z'),

            // ========================================
            // PHONE COLLECTION-SPECIFIC FIELDS (null)
            // ========================================
            'phoneCollectionDetailId' => null,
            'phoneCollectionId' => null,
            'contactType' => null,
            'phoneId' => null,
            'contactDetailId' => null,
            'contactPhoneNumber' => null,
            'contactName' => null,
            'contactRelation' => null,
            'callStatus' => null,
            'callResultId' => null,
            'callResult' => null,
            'leaveMessage' => null,
            'standardRemarkId' => null,
            'standardRemarkContent' => null,
            'standardRemark' => null,
            'askingPostponePayment' => null,
            'dtCallLater' => null,
            'dtCallStarted' => null,
            'dtCallEnded' => null,
            'callDuration' => null,
            'updatePhoneRequest' => null,
            'updatePhoneRemark' => null,
            'reschedulingEvidence' => null,

            // Audit fields
            'createdAt' => \Carbon\Carbon::parse($journal['dtCreated'])->utc()->format('Y-m-d\TH:i:s\Z'),
            'createdBy' => $journal['person_created']['userId'] ?? null,
            'updatedAt' => null,
            'updatedBy' => null,
            'deletedAt' => null,
            'deletedBy' => null,
            'deletedReason' => null,

            // Phone Collection info
            'phoneCollection' => null,
        ];
    }
}
