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
            // Get ALL active batches for past-due
            $batches = TblCcBatch::active()
                ->bySegmentType('past-due')
                ->get(); // Thay đổi từ first() thành get()

            if ($batches->isEmpty()) {
                throw new Exception('No active batches found for past-due segment');
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
            // Get ALL active batches for pre-due
            $batches = TblCcBatch::active()
                ->bySegmentType('pre-due')
                ->get(); // Thay đổi từ first() thành get()

            if ($batches->isEmpty()) {
                throw new Exception('No active batches found for pre-due segment');
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
                $insertData[] = [
                    // Required fields from API
                    'contractId' => $contract['contractId'],
                    'contractNo' => $contract['contractNo'],
                    'contractDate' => $contract['contractDate'],
                    'contractType' => $contract['contractType'],
                    'contractingProductType' => $contract['contractingProductType'],
                    'customerId' => $contract['customerId'],
                    'customerFullName' => $contract['customerFullName'],
                    'gender' => $this->mapGender($contract['gender']),
                    'birthDate' => $contract['birthDate'],
                    'customerAge' => $contract['customerAge'] ?? null,
                    'assetId' => $contract['assetId'],
                    'paymentId' => $contract['paymentId'],
                    'paymentNo' => $contract['paymentNo'],
                    'dueDate' => $contract['dueDate'],
                    'daysOverdueGross' => $contract['daysOverdueGross'],
                    'daysOverdueNet' => $contract['daysOverdueNet'],
                    'daysSinceLastPayment' => $contract['daysSinceLastPayment'],
                    'lastPaymentDate' => $contract['lastPaymentDate'],
                    'paymentAmount' => $contract['paymentAmount'],
                    'penaltyAmount' => $contract['penaltyAmount'],
                    'totalAmount' => $contract['totalAmount'],
                    'amountPaid' => $contract['amountPaid'],
                    'amountUnpaid' => $contract['amountUnpaid'],
                    'hasKYCAppAccount' => $contract['hasKYCAppAccount'] ?? false,

                    // Segment and batch info
                    'segmentType' => $segmentType,
                    'batchId' => $batchId,
                    'riskType' => $contract['preDueRiskSegment'] ?? null,

                    // Default values
                    'status' => 'pending',
                    'totalAttempts' => 0,
                    'createdBy' => 1, // TODO: Get from auth
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
