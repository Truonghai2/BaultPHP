<?php

namespace Core\Console\Commands\Streaming;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Streaming\NatsConsumer;

/**
 * Streaming Consume Command.
 * 
 * Consume events from NATS JetStream.
 * 
 * Usage: php artisan streaming:consume
 */
class StreamingConsumeCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'streaming:consume 
                {--consumer=default : Consumer name}
                {--subjects=events.> : Subjects to consume (comma-separated)}
                {--batch=10 : Batch size}
                {--interval=100 : Poll interval in milliseconds}';
    }

    public function description(): string
    {
        return 'Consume events from NATS JetStream';
    }

    public function handle(): int
    {
        $consumerName = $this->option('consumer');
        $subjects = explode(',', $this->option('subjects'));
        $batchSize = (int) $this->option('batch');
        $interval = (int) $this->option('interval');

        $this->info("Starting NATS consumer: {$consumerName}");
        $this->comment("Subjects: " . implode(', ', $subjects));
        $this->comment("Press Ctrl+C to stop");

        $consumer = $this->app->make(NatsConsumer::class);

        // Handle graceful shutdown
        pcntl_signal(SIGINT, function () use ($consumer) {
            $this->warn("\nShutting down...");
            $consumer->stop();
        });

        $consumer->consume($subjects, function ($event, $message) {
            $this->line("📥 Received: " . get_class($event));
        }, [
            'batch_size' => $batchSize,
            'poll_interval' => $interval,
        ]);

        $this->info("Consumer stopped");

        return 0;
    }
}
