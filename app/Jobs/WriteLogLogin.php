<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;

class WriteLogLogin implements ShouldQueue
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
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('WriteLogLogin job started', ['data' => $this->data]);

            // Format data for Zay Yar's service
            $payload = $this->formatPayload($this->data);

            Log::info('Complete formatted payload', ['payload' => $payload]);

            // Send to RabbitMQ
            $this->publishToRabbitMQ($payload);

            Log::info('WriteLogLogin job completed successfully');

        } catch (Exception $e) {
            Log::error('WriteLogLogin job failed', [
                'error' => $e->getMessage(),
                'data' => $this->data,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Format data payload for Zay Yar's service
     */
    private function formatPayload(array $data): array
    {
        return [
            'event_type' => 'user_login',
            'timestamp' => now()->toISOString(),
            'user_data' => [
                'user_id' => $data['user_id'] ?? null,
                'team_id' => $data['teamId'] ?? '4',
                'email' => $data['email'] ?? 'unknown@example.com',
                'username' => $data['username'] ?? 'unknown',
            ],
            'device_data' => [
                'ip_address' => $data['ip_address'] ?? 'unknown',
                'user_agent' => $data['http_user_agent'] ?? 'unknown',
                'device_type' => $data['device_type'] ?? 'unknown',
                'device_name' => $data['device_name'] ?? 'unknown',
                'browser_name' => $data['browser_name'] ?? 'unknown',
                'platform_name' => $data['platform_name'] ?? 'unknown',
            ],
            'location_data' => [
                'country_name' => $data['country_name'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'region_name' => $data['region_name'] ?? null,
                'region_code' => $data['region_code'] ?? null,
                'city_name' => $data['city_name'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ],
            'metadata' => [
                'source_application' => 'cc-web-app',
                'version' => '1.0.0',
            ]
        ];
    }

    /**
     * Publish message to RabbitMQ
     */
    private function publishToRabbitMQ(array $payload): void
    {
        $connection = new AMQPStreamConnection(
            config('queue.connections.rabbitmq.hosts.0.host'),
            config('queue.connections.rabbitmq.hosts.0.port'),
            config('queue.connections.rabbitmq.hosts.0.user'),
            config('queue.connections.rabbitmq.hosts.0.password'),
            config('queue.connections.rabbitmq.hosts.0.vhost')
        );

        $channel = $connection->channel();

        // Declare exchange
        $exchangeName = config('queue.connections.rabbitmq.options.exchange.name');
        $channel->exchange_declare(
            $exchangeName,
            'direct',
            false,  // passive
            true,   // durable
            false   // auto_delete
        );

        // Declare queue
        $queueName = config('queue.connections.rabbitmq.queue');
        $channel->queue_declare(
            $queueName,
            false,  // passive
            true,   // durable
            false,  // exclusive
            false   // auto_delete
        );

        // Bind queue to exchange
        $channel->queue_bind($queueName, $exchangeName, 'user.login');

        // Create message
        $messageBody = json_encode($payload);
        $message = new AMQPMessage($messageBody, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        // Publish message
        $channel->basic_publish($message, $exchangeName, 'user.login');

        Log::info('Message published to RabbitMQ', [
            'exchange' => $exchangeName,
            'queue' => $queueName,
            'routing_key' => 'user.login',
            'payload_size' => strlen($messageBody)
        ]);

        $channel->close();
        $connection->close();
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('WriteLogLogin job failed permanently', [
            'exception' => $exception->getMessage(),
            'data' => $this->data
        ]);
    }
}
