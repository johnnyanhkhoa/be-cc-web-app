<?php

namespace App\Console\Commands;

use App\Services\PhoneCollectionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncPhoneCollections extends Command
{
    protected $signature = 'sync:phone-collections
                          {--force : Force sync even if not scheduled time}';

    protected $description = 'Sync phone collections from external API daily at 6:00 AM Vietnam time';

    protected $syncService;

    public function __construct(PhoneCollectionSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle()
    {
        try {
            $this->info('Starting phone collection sync...');

            // Check if it's the right time (5:30 AM Vietnam time) unless forced
            if (!$this->option('force') && !$this->isScheduledTime()) {
                $this->warn('Not the scheduled time for sync. Use --force to override.');
                return 1;
            }

            // Execute sync
            $results = $this->syncService->syncPhoneCollections();

            // Display results
            $this->displayResults($results);

            $this->info('Phone collection sync completed successfully!');
            return 0;

        } catch (Exception $e) {
            $this->error('Phone collection sync failed: ' . $e->getMessage());
            Log::error('Command sync:phone-collections failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    protected function isScheduledTime(): bool
    {
        // Check if current time is 6:00 AM Vietnam time (UTC+7)
        $vietnamTime = now()->setTimezone('Asia/Ho_Chi_Minh');
        $scheduledHour = 6;
        $scheduledMinute = 0;

        return $vietnamTime->hour === $scheduledHour &&
            $vietnamTime->minute >= $scheduledMinute &&
            $vietnamTime->minute < ($scheduledMinute + 30); // 30 minute window
    }

    protected function displayResults(array $results): void
    {
        $this->info("\n=== Sync Results ===");

        foreach ($results as $segmentType => $result) {
            $this->info("\n{$segmentType} segment:");
            $this->line("  Batches processed: {$result['batches_processed']}");
            $this->line("  Total contracts: {$result['total_contracts']}");
            $this->line("  Total inserted: {$result['total_inserted']}");

            $this->info("  Batch details:");
            foreach ($result['batch_results'] as $batch) {
                $this->line("    - Batch ID {$batch['batch_id']} ({$batch['batch_code']}): {$batch['contracts']} contracts, {$batch['inserted']} inserted");
            }
        }
    }
}
