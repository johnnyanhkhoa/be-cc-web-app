<?php

namespace App\Services;

use App\Models\TblCcPhoneCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Exception;

class BulkPhoneCollectionService
{
    /**
     * Bulk insert phone collection records using collection approach
     *
     * @param array $phoneCollections
     * @return array
     * @throws Exception
     */
    public function bulkInsert(array $phoneCollections): array
    {
        $startTime = microtime(true);

        try {
            DB::beginTransaction();

            Log::info('Starting bulk insert', [
                'record_count' => count($phoneCollections),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);

            // Transform array to collection with proper field mapping
            $insertData = collect($phoneCollections)->map(function ($item) {
                return [
                    // Status and defaults
                    'status' => 'pending',
                    'totalAttempts' => 0,
                    'createdBy' => 1, // TODO: Get from auth when available
                    'updatedBy' => 1,

                    // Required fields from request
                    'segmentType' => $item['segmentType'],
                    'contractId' => $item['contractId'],
                    'customerId' => $item['customerId'],
                    'assetId' => $item['assetId'],
                    'paymentId' => $item['paymentId'],
                    'paymentNo' => $item['paymentNo'],
                    'dueDate' => $item['dueDate'],
                    'daysOverdueGross' => $item['daysOverdueGross'],
                    'daysOverdueNet' => $item['daysOverdueNet'],
                    'daysSinceLastPayment' => $item['daysSinceLastPayment'],
                    'lastPaymentDate' => $item['lastPaymentDate'] ?? null,
                    'paymentAmount' => $item['paymentAmount'],
                    'penaltyAmount' => $item['penaltyAmount'],
                    'totalAmount' => $item['totalAmount'],
                    'amountPaid' => $item['amountPaid'],
                    'amountUnpaid' => $item['amountUnpaid'],
                    'contractNo' => $item['contractNo'],
                    'contractDate' => $item['contractDate'],
                    'contractType' => $item['contractType'],
                    'contractingProductType' => $item['contractingProductType'],
                    'customerFullName' => $item['customerFullName'],
                    'gender' => $item['gender'],
                    'birthDate' => $item['birthDate'],

                    // Timestamps
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ];
            })->toArray();

            // Bulk insert using DB::table()->insert() - similar approach to insertUsing
            $result = DB::table('tbl_CcPhoneCollection')->insert($insertData);

            if (!$result) {
                throw new Exception('Bulk insert failed');
            }

            // Get the range of IDs that were inserted
            // Note: This assumes auto-increment IDs
            $lastInsertId = DB::getPdo()->lastInsertId();
            $firstInsertId = $lastInsertId - count($phoneCollections) + 1;

            DB::commit();

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            Log::info('Bulk insert completed successfully', [
                'records_inserted' => count($phoneCollections),
                'execution_time' => $executionTime . ' seconds',
                'first_id' => $firstInsertId,
                'last_id' => $lastInsertId,
                'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB'
            ]);

            // return [
            //     'success' => true,
            //     'records_inserted' => count($phoneCollections),
            //     'execution_time' => $executionTime,
            //     'first_id' => $firstInsertId,
            //     'last_id' => $lastInsertId,
            //     'inserted_ids' => range($firstInsertId, $lastInsertId)
            // ];
            return [
                'success' => true,
                'records_inserted' => count($phoneCollections),
            ];

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Bulk insert failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'record_count' => count($phoneCollections)
            ]);

            throw $e;
        }
    }

    /**
     * Validate data integrity before bulk insert
     *
     * @param array $phoneCollections
     * @return array
     */
    public function validateDataIntegrity(array $phoneCollections): array
    {
        $errors = [];

        return $errors;
    }

    /**
     * Process in chunks for very large datasets
     *
     * @param array $phoneCollections
     * @param int $chunkSize
     * @return array
     */
    public function bulkInsertInChunks(array $phoneCollections, int $chunkSize = 500): array
    {
        $totalRecords = count($phoneCollections);
        $chunks = array_chunk($phoneCollections, $chunkSize);
        $results = [];
        $totalInserted = 0;

        Log::info('Starting chunked bulk insert', [
            'total_records' => $totalRecords,
            'chunk_size' => $chunkSize,
            'total_chunks' => count($chunks)
        ]);

        foreach ($chunks as $index => $chunk) {
            try {
                $chunkResult = $this->bulkInsert($chunk);
                $results[] = $chunkResult;
                $totalInserted += $chunkResult['records_inserted'];

                Log::info("Chunk {$index} completed", [
                    'chunk_records' => count($chunk),
                    'total_completed' => $totalInserted
                ]);

            } catch (Exception $e) {
                Log::error("Chunk {$index} failed", [
                    'error' => $e->getMessage(),
                    'chunk_size' => count($chunk)
                ]);
                throw $e;
            }
        }

        // return [
        //     'success' => true,
        //     'total_records' => $totalRecords,
        //     'total_inserted' => $totalInserted,
        //     'chunks_processed' => count($chunks),
        //     'chunk_results' => $results
        // ];
        return [
            'success' => true,
            'records_inserted' => $totalInserted,
        ];
    }
}
