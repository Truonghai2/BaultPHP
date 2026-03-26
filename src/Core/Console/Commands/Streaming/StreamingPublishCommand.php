<?php

namespace Core\Console\Commands\Streaming;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Streaming\NatsEventBus;

/**
 * Streaming Publish Command.
 * 
 * Publish test event to NATS JetStream.
 * 
 * Usage: php artisan streaming:publish
 */
class StreamingPublishCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'streaming:publish 
                {--count=1 : Number of events to publish}
                {--event=TestEvent : Event type}';
    }

    public function description(): string
    {
        return 'Publish test events to NATS JetStream';
    }

    public function handle(): int
    {
        $count = (int) $this->option('count');
        $eventType = $this->option('event');

        $this->info("Publishing {$count} events...");

        $eventBus = $this->app->make(NatsEventBus::class);

        for ($i = 1; $i <= $count; $i++) {
            $event = $this->createTestEvent($eventType, $i);
            
            try {
                $eventBus->publish($event);
                $this->line("✅ Published event {$i}/{$count}");
            } catch (\Throwable $e) {
                $this->error("❌ Failed to publish event {$i}: " . $e->getMessage());
            }
        }

        $this->info("✅ Published {$count} events successfully!");

        return 0;
    }

    /**
     * Create test event.
     */
    protected function createTestEvent(string $type, int $index): object
    {
        return new class($type, $index) {
            public function __construct(
                public string $type,
                public int $index,
                public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
            ) {
            }

            public function getEventId(): string
            {
                return uniqid('test-', true);
            }

            public function getCorrelationId(): string
            {
                return uniqid('corr-', true);
            }

            public function getOccurredAt(): \DateTimeImmutable
            {
                return $this->occurredAt;
            }
        };
    }
}
