<?php

namespace App\Providers;

use Core\Application;
use Core\Streaming\NatsConnection;
use Core\Streaming\NatsEventBus;
use Core\Streaming\NatsConsumer;
use Core\Streaming\EventSerializer;
use Core\Streaming\ProtobufEventSerializer;
use Core\Streaming\JsonEventSerializer;
use Core\Events\EventStore;
use Core\Events\ProtobufEventStore;
use Psr\Log\LoggerInterface;

/**
 * Streaming Service Provider.
 * 
 * Registers NATS JetStream and Protobuf serialization.
 */
class StreamingServiceProvider
{
    public function __construct(
        protected Application $app
    ) {
    }

    public function register(): void
    {
        // Register NATS Connection
        $this->app->singleton(NatsConnection::class, function ($app) {
            $config = config('streaming.connection');
            
            return new NatsConnection(
                host: $config['host'],
                port: $config['port'],
                logger: $app->make(LoggerInterface::class),
                user: $config['user'],
                password: $config['password'],
            );
        });

        // Register Event Serializer
        $this->app->singleton(EventSerializer::class, function ($app) {
            $serializer = config('streaming.serializer', 'protobuf');
            
            return match ($serializer) {
                'protobuf' => new ProtobufEventSerializer(),
                'json' => new JsonEventSerializer(),
                default => new JsonEventSerializer(),
            };
        });

        // Register NATS Event Bus
        $this->app->singleton(NatsEventBus::class, function ($app) {
            return new NatsEventBus(
                $app->make(NatsConnection::class),
                $app->make(EventSerializer::class),
                $app->make(LoggerInterface::class),
                config('streaming.streams.events.name', 'EVENTS')
            );
        });

        // Register NATS Consumer
        $this->app->bind(NatsConsumer::class, function ($app) {
            return new NatsConsumer(
                $app->make(NatsConnection::class),
                $app->make(EventSerializer::class),
                $app->make(LoggerInterface::class),
                config('streaming.streams.events.name', 'EVENTS'),
                config('streaming.consumers.projections.durable_name', 'default-consumer')
            );
        });

        // Register Protobuf Event Store (if using protobuf)
        if (config('streaming.serializer') === 'protobuf') {
            $this->app->singleton(EventStore::class, function ($app) {
                return new ProtobufEventStore(
                    $app->make(\PDO::class),
                    $app->make(LoggerInterface::class),
                    new ProtobufEventSerializer()
                );
            });
        }
    }

    public function boot(): void
    {
        // Initialize NATS streams if enabled
        if (config('streaming.enabled', true)) {
            $this->initializeStreams();
        }
    }

    /**
     * Initialize NATS streams.
     */
    protected function initializeStreams(): void
    {
        try {
            $connection = $this->app->make(NatsConnection::class);
            $streams = config('streaming.streams', []);

            foreach ($streams as $streamConfig) {
                $name = $streamConfig['name'];
                
                // Check if stream exists
                try {
                    $connection->getStream($name);
                } catch (\Throwable $e) {
                    // Stream doesn't exist, create it
                    $connection->createStream($name, $streamConfig);
                }
            }
        } catch (\Throwable $e) {
            // Log error but don't block boot
            logger()->warning("Failed to initialize NATS streams", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
