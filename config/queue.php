<?php

use PhpAmqpLib\Connection\AMQPLazyConnection;

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'connection' => PhpAmqpLib\Connection\AMQPStreamConnection::class,
            'hosts' => [
                [
                    'host'      => env('RABBITMQ_EXPORT_HOST', '127.0.0.1'),
                    'port'      => env('RABBITMQ_EXPORT_PORT', 5672),
                    'user'      => env('RABBITMQ_EXPORT_USER', 'guest'),
                    'password'  => env('RABBITMQ_EXPORT_PASSWORD', 'guest'),
                    'vhost'     => env('RABBITMQ_EXPORT_VHOST', '/'),
                ],
            ],
            'queue' => env('RABBITMQ_EXPORT_QUEUE', 'cc_module'),
            'options' => [
                'exchange' => [
                    'name'        => env('RABBITMQ_EXPORT_EXCHANGE_NAME', 'cc_exchange'),
                    'type'        => 'direct',
                    'declare'     => true,
                    'passive'     => false,
                    'durable'     => true,
                    'auto_delete' => false,
                ],
                'queue' => [
                    'job' => VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob::class,
                ],
                'heartbeat' => 120,
                'read_write_timeout' => 300,
            ],
            'retry_after' => 90,
            'timeout'     => 60,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],
];
