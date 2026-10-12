<?php

namespace App\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class QueueConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $queueConfig = site_settings('queue_connection_config', config('setting.site_settings.queue_connection_config', [
            'driver' => 'database',
            'connection' => [
                'host' => null,
                'port' => null,
                'database' => null,
                'username' => null,
                'password' => null,
            ],
        ]));
        
        if(gettype($queueConfig) == "string") $queueConfig = json_decode($queueConfig, true);
        
        config(['queue.default' => Arr::get($queueConfig, 'driver', 'database')]);

        if (Arr::get($queueConfig, 'driver') === 'redis') {
            config([
                'queue.connections.redis.host' => Arr::get($queueConfig, 'connection.host', env('REDIS_HOST', '127.0.0.1')),
                'queue.connections.redis.port' => Arr::get($queueConfig, 'connection.port', env('REDIS_PORT', '6379')),
                'queue.connections.redis.database' => Arr::get($queueConfig, 'connection.database', '0'),
                'queue.connections.redis.username' => Arr::get($queueConfig, 'connection.username', null),
                'queue.connections.redis.password' => Arr::get($queueConfig, 'connection.password', null),
            ]);
        } elseif (Arr::get($queueConfig, 'driver') === 'beanstalkd') {
            config([
                'queue.connections.beanstalkd.host' => Arr::get($queueConfig, 'connection.host', 'localhost'),
                'queue.connections.beanstalkd.port' => Arr::get($queueConfig, 'connection.port', '11300'),
            ]);
        } elseif (Arr::get($queueConfig, 'driver') === 'sqs') {
            config([
                'queue.connections.sqs.key' => Arr::get($queueConfig, 'connection.key', env('AWS_ACCESS_KEY_ID')),
                'queue.connections.sqs.secret' => Arr::get($queueConfig, 'connection.secret', env('AWS_SECRET_ACCESS_KEY')),
                'queue.connections.sqs.prefix' => Arr::get($queueConfig, 'connection.prefix', env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id')),
                'queue.connections.sqs.queue' => Arr::get($queueConfig, 'connection.queue', env('SQS_QUEUE', 'default')),
                'queue.connections.sqs.region' => Arr::get($queueConfig, 'connection.region', env('AWS_DEFAULT_REGION', 'us-east-1')),
            ]);
        }
        // Sync and database require no credentials
    }
}