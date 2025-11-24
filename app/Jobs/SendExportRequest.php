<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendExportRequest implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $data;
    public $timeout = 120;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {
        $this->data = $data;

        // Chỉ định queue sẽ gửi đến
        $this->onQueue('cc_module');
        $this->onConnection('rabbitmq');
    }

    /**
     * Execute the job.
     *
     * Theo Zay Yar: "no need to write anything in handle function"
     */
    public function handle(): void
    {
        // Không cần xử lý gì ở đây
        // Job này chỉ để push data lên RabbitMQ
        // MAXIMUS (Linh) sẽ consume và xử lý

        Log::info('Export request sent to RabbitMQ', [
            'data' => $this->data
        ]);
    }
}
