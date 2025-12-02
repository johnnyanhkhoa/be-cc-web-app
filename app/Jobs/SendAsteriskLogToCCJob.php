<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SendAsteriskLogToCCJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct()
    {
        $this->onQueue('asterisk_log');
    }

    public function handle(): void
    {
        try {
            if (!isset($this->job)) {
                Log::warning('Job property not available');
                return;
            }

            $payload = $this->job->payload();

            // Parse serialized command
            if (isset($payload['data']['command'])) {
                $command = unserialize($payload['data']['command']);

                if (isset($command->data) && is_array($command->data)) {
                    $asteriskData = $command->data;

                    Log::info('=== ASTERISK CALL LOG RECEIVED ===', [
                        'api_call_id' => $asteriskData['api_call_id'] ?? null,
                        'case_id' => $asteriskData['case_id'] ?? null,
                        'asterisk_call_id' => $asteriskData['asterisk_call_id'] ?? null,
                    ]);

                    // Save to database
                    $this->saveToDatabase($asteriskData);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to process Asterisk log', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw để job vào failed queue
            throw $e;
        }
    }

    protected function saveToDatabase(array $data): void
    {
        try {
            // Parse calledAt if it's a Carbon object
            $calledAt = null;
            if (isset($data['called_at'])) {
                if ($data['called_at'] instanceof Carbon) {
                    $calledAt = $data['called_at'];
                } else {
                    $calledAt = Carbon::parse($data['called_at']);
                }
            }

            // Parse callDate
            $callDate = isset($data['call_date']) ? Carbon::parse($data['call_date']) : null;

            DB::table('tbl_CcAsteriskCallLog')->insert([
                'caseId' => $data['case_id'] ?? null,
                'phoneNo' => $data['phone_to'] ?? null,
                'phoneExtension' => $data['extension_no'] ?? null,
                'username' => $data['username'] ?? null,
                'apiCallId' => $data['api_call_id'] ?? null,
                'callDate' => $callDate,
                'calledAt' => $calledAt,
                'handleTimeSec' => isset($data['handle_time_sec']) ? (int)$data['handle_time_sec'] : null,
                'talkTimeSec' => isset($data['talk_time_sec']) ? (int)$data['talk_time_sec'] : null,
                'callStatus' => $data['status'] ?? null,
                'recordFile' => $data['record_file'] ?? null,
                'asteriskCallId' => $data['asterisk_call_id'] ?? null,
                'rawContent' => $data['raw_content'] ?? null,
                'company' => $data['company'] ?? null,
                'outboundCnum' => $data['outbound_cnum'] ?? null,
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);

            Log::info('Asterisk call log saved successfully', [
                'case_id' => $data['case_id'] ?? null,
                'asterisk_call_id' => $data['asterisk_call_id'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save Asterisk log to database', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }
}
