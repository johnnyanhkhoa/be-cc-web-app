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

            $insertData = [];
            foreach ($contracts as $contract) {
                // ✅ NEW LOGIC: Map batchId 5,6,7 to parent batch 8
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

                    // === Contact info (NEW) ===
                    'phoneNo1' => $contract['phoneNo1'] ?? null,
                    'phoneNo2' => $contract['phoneNo2'] ?? null,
                    'phoneNo3' => $contract['phoneNo3'] ?? null,
                    'homeAddress' => $contract['homeAddress'] ?? null,

                    // === Location info (NEW) ===
                    'contractPlaceId' => $contract['contractPlaceId'] ?? null,
                    'contractPlaceName' => $contract['contractPlaceName'] ?? null,
                    'salesAreaId' => $contract['salesAreaId'] ?? null,
                    'salesAreaName' => $contract['salesAreaName'] ?? null,

                    // === Asset info ===
                    'assetId' => $contract['assetId'],
                    'productName' => $contract['productName'] ?? null,      // NEW
                    'productColor' => $contract['productColor'] ?? null,    // NEW
                    'plateNo' => $contract['plateNo'] ?? null,              // NEW
                    'unitPrice' => $contract['unitPrice'] ?? null,          // NEW

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
                    'totalAmount' => $contract['totalAmount'],
                    'amountPaid' => $contract['amountPaid'],
                    'amountUnpaid' => $contract['amountUnpaid'],

                    // === Payment status (FIXED by Maximus) ===
                    'paymentStatus' => $contract['paymentStatus'] ?? null,

                    // === Penalty & Reschedule ===
                    'penaltyExempted' => isset($contract['penaltyExempted']) && $contract['penaltyExempted'] == 1,
                    'reschedule' => $contract['reschedule'] ?? null,

                    // === Penalty details (NEW: Added by Maximus) ===
                    'totalPenaltyFeesCharged' => $contract['totalPenaltyAmountCharged'] ?? null,
                    'noOfPenaltyFeesExempted' => $contract['noOfPenaltyFeesExempted'] ?? null,
                    'noOfPenaltyFeesPaid' => $contract['noOfPenaltyFeesPaid'] ?? null,
                    'totalPenaltyAmountCharged' => $contract['totalPenaltyAmountCharged'] ?? null,

                    // === Account & Segment info ===
                    'hasKYCAppAccount' => isset($contract['hasKYCAppAccount']) && $contract['hasKYCAppAccount'] == 1,
                    'segmentType' => $segmentType,
                    'batchId' => $finalBatchId,        // 5,6,7 → 8; others stay same
                    'subBatchId' => $originalBatchId,  // Keep original batchId
                    'riskType' => $contract['preDueRiskSegment'] ?? null,

                    // === Default values ===
                    'status' => 'pending',
                    'totalAttempts' => 0,
                    'createdBy' => 1,
                    'updatedBy' => 1,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ];
            }

            // Bulk insert
            DB::table('tbl_CcPhoneCollection')->insert($insertData);

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
