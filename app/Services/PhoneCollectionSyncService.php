<?php

namespace App\Services;

use App\Models\TblCcBatch;
use App\Models\TblCcPhoneCollection;
use App\Services\ExternalApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PhoneCollectionSyncService
{
    protected $externalApiService;

    public function __construct(ExternalApiService $externalApiService)
    {
        $this->externalApiService = $externalApiService;
    }

    /**
     * Sync phone collections for both past-due and pre-due
     */
    public function syncPhoneCollections(): array
    {
        $results = [];

        try {
            // Sync past-due contracts
            $pastDueResult = $this->syncPastDueContracts();
            $results['past-due'] = $pastDueResult;

            // Sync pre-due contracts
            $preDueResult = $this->syncPreDueContracts();
            $results['pre-due'] = $preDueResult;

            Log::info('Phone collection sync completed', $results);

            return $results;

        } catch (Exception $e) {
            Log::error('Phone collection sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Sync past-due contracts
     */
    protected function syncPastDueContracts(): array
    {
        try {
            // Get active batches for past-due with intensity NOT NULL
            $batches = TblCcBatch::active()
                ->bySegmentType('past-due')
                ->whereNotNull('intensity')
                ->get();

            if ($batches->isEmpty()) {
                throw new Exception('No active batches with intensity found for past-due segment');
            }

            $totalContracts = 0;
            $totalInserted = 0;
            $batchResults = [];

            foreach ($batches as $batch) {
                Log::info('Processing past-due batch', [
                    'batch_id' => $batch->batchId,
                    'batch_code' => $batch->code
                ]);

                // Fetch contracts from external API
                $apiResponse = $this->externalApiService->fetchPastDueContracts([
                    'type' => $batch->type,
                    'code' => $batch->code,
                    'intensity' => $batch->intensity
                ]);

                // Transform and insert data
                $insertedCount = $this->insertPhoneCollections(
                    $apiResponse['data']['data'] ?? [],
                    'past-due',
                    $batch->batchId
                );

                $batchContracts = $apiResponse['data']['total'] ?? 0;
                $totalContracts += $batchContracts;
                $totalInserted += $insertedCount;

                $batchResults[] = [
                    'batch_id' => $batch->batchId,
                    'batch_code' => $batch->code,
                    'contracts' => $batchContracts,
                    'inserted' => $insertedCount
                ];
            }

            return [
                'success' => true,
                'batches_processed' => count($batches),
                'batch_results' => $batchResults,
                'total_contracts' => $totalContracts,
                'total_inserted' => $totalInserted
            ];

        } catch (Exception $e) {
            Log::error('Past-due sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sync pre-due contracts
     */
    protected function syncPreDueContracts(): array
    {
        try {
            // Get active batches for pre-due with intensity NOT NULL
            $batches = TblCcBatch::active()
                ->bySegmentType('pre-due')
                ->whereNotNull('intensity')
                ->get();

            if ($batches->isEmpty()) {
                throw new Exception('No active batches with intensity found for pre-due segment');
            }

            $totalContracts = 0;
            $totalInserted = 0;
            $batchResults = [];

            foreach ($batches as $batch) {
                Log::info('Processing pre-due batch', [
                    'batch_id' => $batch->batchId,
                    'batch_code' => $batch->code
                ]);

                // Fetch contracts from external API
                $apiResponse = $this->externalApiService->fetchPreDueContracts([
                    'type' => $batch->type,
                    'code' => $batch->code,
                    'intensity' => $batch->intensity
                ]);

                // Transform and insert data
                $insertedCount = $this->insertPhoneCollections(
                    $apiResponse['data']['data'] ?? [],
                    'pre-due',
                    $batch->batchId
                );

                $batchContracts = $apiResponse['data']['total'] ?? 0;
                $totalContracts += $batchContracts;
                $totalInserted += $insertedCount;

                $batchResults[] = [
                    'batch_id' => $batch->batchId,
                    'batch_code' => $batch->code,
                    'contracts' => $batchContracts,
                    'inserted' => $insertedCount
                ];
            }

            return [
                'success' => true,
                'batches_processed' => count($batches),
                'batch_results' => $batchResults,
                'total_contracts' => $totalContracts,
                'total_inserted' => $totalInserted
            ];

        } catch (Exception $e) {
            Log::error('Pre-due sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Transform API data and insert into tbl_CcPhoneCollection
     */
    protected function insertPhoneCollections(array $contracts, string $segmentType, int $batchId): int
    {
        if (empty($contracts)) {
            return 0;
        }

        try {
            DB::beginTransaction();

            $excludedFromNew = \App\Models\TblCcPromiseHistory::where('isActive', true)
                ->where(function($query) {
                    $query->where('promisedPaymentDate', '>', now()->toDateString())
                        ->orWhere('dtCallLater', '>', now());
                })
                ->pluck('paymentId')
                ->toArray();

            // Get excluded payments from old table
            $excludedFromOld = DB::table('tbl_CcPromiseHistory_Old')
                ->where('isActive', true)
                ->where(function($query) {
                    $query->where('promisedPaymentDate', '>', now()->toDateString())
                        ->orWhere('dtCallLater', '>', now());
                })
                ->pluck('paymentId')
                ->toArray();

            // Merge and remove duplicates
            $excludedPaymentIds = array_unique(array_merge($excludedFromNew, $excludedFromOld));

            if (!empty($excludedPaymentIds)) {
                Log::info('Excluding payments with active promises from sync', [
                    'segment_type' => $segmentType,
                    'batch_id' => $batchId,
                    'excluded_count' => count($excludedPaymentIds),
                    'excluded_payment_ids' => array_slice($excludedPaymentIds, 0, 10) // Log first 10 for debugging
                ]);

                // Filter contracts array
                $contracts = array_filter($contracts, function($contract) use ($excludedPaymentIds) {
                    return !in_array($contract['paymentId'] ?? null, $excludedPaymentIds);
                });

                // Re-index array after filtering
                $contracts = array_values($contracts);

                Log::info('Contracts after promise filtering', [
                    'segment_type' => $segmentType,
                    'batch_id' => $batchId,
                    'remaining_count' => count($contracts)
                ]);
            }

            $insertData = [];
            foreach ($contracts as $index => $contract) {
                try {
                    // ✅ Map batchId 5,6,7 to parent batch 8
                    $originalBatchId = $batchId;
                    $finalBatchId = in_array($batchId, [5, 6, 7]) ? 8 : $batchId;

                    $insertData[] = [
                        // === Basic contract info ===
                        'contractId' => $contract['contractId'],
                        'contractNo' => $contract['contractNo'],
                        'contractDate' => $contract['contractDate'],
                        'contractType' => $contract['contractType'],
                        'contractingProductType' => $contract['contractingProductType'],

                        // === Customer info ===
                        'customerId' => $contract['customerId'],
                        'customerFullName' => $contract['customerFullName'],
                        'gender' => $this->mapGender($contract['gender']),
                        'birthDate' => $contract['birthDate'],
                        'customerAge' => $contract['customerAge'] ?? null,

                        // === Contact info ===
                        'phoneNo1' => $contract['phoneNo1'] ?? null,
                        'phoneNo2' => $contract['phoneNo2'] ?? null,
                        'phoneNo3' => $contract['phoneNo3'] ?? null,
                        'homeAddress' => $contract['homeAddress'] ?? null,

                        // === Location info ===
                        'contractPlaceId' => $contract['contractPlaceId'] ?? null,
                        'contractPlaceName' => $contract['contractPlaceName'] ?? null,
                        'salesAreaId' => $contract['salesAreaId'] ?? null,
                        'salesAreaName' => $contract['salesAreaName'] ?? null,

                        // === Asset info ===
                        'assetId' => $contract['assetId'],
                        'productName' => $contract['productName'] ?? null,
                        'productColor' => $contract['productColor'] ?? null,
                        'plateNo' => $contract['plateNo'] ?? null,
                        'unitPrice' => $contract['unitPrice'] ?? null,

                        // === Payment info ===
                        'paymentId' => $contract['paymentId'],
                        'paymentNo' => $contract['paymentNo'],
                        'dueDate' => $contract['dueDate'],
                        'daysOverdueGross' => $contract['daysOverdueGross'],
                        'daysOverdueNet' => $contract['daysOverdueNet'],
                        'daysSinceLastPayment' => $contract['daysSinceLastPayment'] ?? null,
                        'lastPaymentDate' => $contract['lastPaymentDate'] ?? null,
                        'paymentAmount' => $contract['paymentAmount'],
                        'penaltyAmount' => $contract['penaltyAmount'],
                        'totalAmount' => $contract['totalAmount'] ?? $contract['totalOvdAmount'] ?? null,
                        'amountPaid' => $contract['amountPaid'],
                        'amountUnpaid' => $contract['amountUnpaid'],

                        // === Payment status ===
                        'paymentStatus' => $contract['paymentStatus'] ?? null,

                        // === Penalty & Reschedule ===
                        'penaltyExempted' => isset($contract['penaltyExempted']) && $contract['penaltyExempted'] == 1,
                        'reschedule' => $contract['reschedule'] ?? null,

                        // === Penalty details ===
                        'noOfPenaltyFeesCharged' => $contract['noOfPenaltyFeesCharged'] ?? null,
                        'noOfPenaltyFeesExempted' => $contract['noOfPenaltyFeesExempted'] ?? null,
                        'noOfPenaltyFeesPaid' => $contract['noOfPenaltyFeesPaid'] ?? null,
                        'totalPenaltyAmountCharged' => $contract['totalPenaltyAmountCharged'] ?? null,

                        // === Account & Segment info ===
                        'hasKYCAppAccount' => isset($contract['hasKYCAppAccount']) && $contract['hasKYCAppAccount'] == 1,
                        'segmentType' => $segmentType,
                        'batchId' => $finalBatchId,
                        'subBatchId' => $originalBatchId,
                        'riskType' => $contract['preDueRiskSegment'] ?? null,

                        // === Default values ===
                        'status' => 'pending',
                        'totalAttempts' => 0,
                        'createdBy' => 1,
                        'updatedBy' => 1,
                        'createdAt' => now(),
                        'updatedAt' => now(),
                    ];
                } catch (\Exception $e) {
                    Log::error('Error processing contract', [
                        'segment_type' => $segmentType,
                        'batch_id' => $batchId,
                        'contract_id' => $contract['contractId'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (empty($insertData)) {
            Log::info('No new contracts to insert', [
                'segment_type' => $segmentType,
                'batch_id' => $batchId
            ]);
            DB::commit();
            return 0;
        }

        // ✅ Bulk insert với chunks (500 records mỗi lần)
        $chunks = array_chunk($insertData, 500);
        foreach ($chunks as $chunk) {
            DB::table('tbl_CcPhoneCollection')->insert($chunk);
        }

        DB::commit();

            Log::info('Phone collections inserted successfully', [
                'segment_type' => $segmentType,
                'batch_id' => $batchId,
                'inserted_count' => count($insertData)
            ]);

            return count($insertData);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to insert phone collections', [
                'segment_type' => $segmentType,
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    /**
     * Map gender from API response to database format
     */
    protected function mapGender(string $gender): string
    {
        return match($gender) {
            'm' => 'male',
            'f' => 'female',
            default => 'other'
        };
    }
}
