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

            Log::info('=== RAW PAYLOAD RECEIVED ===', [
                'payload' => $payload
            ]);

            // Parse serialized command
            if (isset($payload['data']['command'])) {
                $command = unserialize($payload['data']['command']);

                // ✅ FIX: Access private property bằng Reflection
                $asteriskData = null;

                if (is_object($command)) {
                    try {
                        // Sử dụng Reflection để access private property
                        $reflection = new \ReflectionClass($command);
                        $property = $reflection->getProperty('data');
                        $property->setAccessible(true);
                        $asteriskData = $property->getValue($command);
                    } catch (\ReflectionException $e) {
                        Log::warning('Cannot access data property via reflection', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                if ($asteriskData && is_array($asteriskData)) {
                    Log::info('=== ASTERISK CALL LOG RECEIVED ===', [
                        'api_call_id' => $asteriskData['api_call_id'] ?? null,
                        'case_id' => $asteriskData['case_id'] ?? null,
                        'asterisk_call_id' => $asteriskData['asterisk_call_id'] ?? null,
                        'phone_to' => $asteriskData['phone_to'] ?? null,
                    ]);

                    $this->saveToDatabase($asteriskData);
                } else {
                    Log::warning('Invalid asterisk data structure', [
                        'command_class' => get_class($command),
                        'data_type' => gettype($asteriskData),
                        'data_value' => $asteriskData,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to process Asterisk log', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function saveToDatabase(array $data): void
    {
        try {
            // Parse dates
            $calledAt = null;
            if (isset($data['called_at'])) {
                if ($data['called_at'] instanceof Carbon) {
                    $calledAt = $data['called_at'];
                } else {
                    $calledAt = Carbon::parse($data['called_at']);
                }
            }

            $callDate = isset($data['call_date']) ? Carbon::parse($data['call_date']) : null;

            // ✅ TÌM record đã tạo khi INITIATE (match bằng caseId + phoneNo + createdAt gần nhất)
            $existingLog = DB::table('tbl_CcAsteriskCallLog')
                ->where('caseId', $data['case_id'])
                ->where('phoneNo', $data['phone_to'])
                ->whereNull('apiCallId') // Chỉ lấy record chưa có apiCallId
                ->orderBy('createdAt', 'desc')
                ->first();

            if ($existingLog) {
                // ✅ UPDATE record đã có
                DB::table('tbl_CcAsteriskCallLog')
                    ->where('id', $existingLog->id)
                    ->update([
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
                        'updatedAt' => now(),
                    ]);

                Log::info('Asterisk call log UPDATED successfully', [
                    'id' => $existingLog->id,
                    'case_id' => $data['case_id'],
                    'asterisk_call_id' => $data['asterisk_call_id'],
                ]);
            } else {
                // ✅ INSERT nếu không tìm thấy (fallback - trường hợp không initiate trước)
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

                Log::info('Asterisk call log INSERTED (no initiate record found)', [
                    'case_id' => $data['case_id'],
                    'asterisk_call_id' => $data['asterisk_call_id'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to save Asterisk log to database', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }
}
