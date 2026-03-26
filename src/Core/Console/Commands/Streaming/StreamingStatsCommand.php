<?php

namespace Core\Console\Commands\Streaming;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Streaming\NatsConnection;
use Core\Events\ProtobufEventStore;

/**
 * Streaming Stats Command.
 * 
 * Show NATS and EventStore statistics.
 * 
 * Usage: php artisan streaming:stats
 */
class StreamingStatsCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'streaming:stats';
    }

    public function description(): string
    {
        return 'Show streaming and event store statistics';
    }

    public function handle(): int
    {
        $this->info("📊 Streaming Statistics");
        $this->line("");

        // NATS Stats
        $this->showNatsStats();
        $this->line("");

        // EventStore Stats
        $this->showEventStoreStats();

        return 0;
    }

    /**
     * Show NATS statistics.
     */
    protected function showNatsStats(): void
    {
        try {
            $connection = $this->app->make(NatsConnection::class);
            
            $this->comment("NATS Connection:");
            $this->line("  Status: " . ($connection->isConnected() ? "✅ Connected" : "❌ Disconnected"));
            $this->line("  Ping: " . ($connection->ping() ? "✅ OK" : "❌ Failed"));

            // Get stream info
            $streams = config('streaming.streams', []);
            $this->line("");
            $this->comment("Streams:");

            foreach ($streams as $streamConfig) {
                try {
                    $stream = $connection->getStream($streamConfig['name']);
                    $this->line("  - {$streamConfig['name']}: ✅ Available");
                } catch (\Throwable $e) {
                    $this->line("  - {$streamConfig['name']}: ❌ Not found");
                }
            }
        } catch (\Throwable $e) {
            $this->error("Failed to get NATS stats: " . $e->getMessage());
        }
    }

    /**
     * Show EventStore statistics.
     */
    protected function showEventStoreStats(): void
    {
        try {
            $eventStore = $this->app->make(\Core\Events\EventStore::class);

            if ($eventStore instanceof ProtobufEventStore) {
                $stats = $eventStore->getStorageStats();

                $this->comment("Event Store:");
                $this->line("  Total Events: " . number_format($stats['total_events']));
                $this->line("  Protobuf Events: " . number_format($stats['protobuf_events']));
                $this->line("  JSON Events: " . number_format($stats['json_events']));
                $this->line("  Average Size: " . round($stats['avg_size']) . " bytes");
                $this->line("  Total Size: " . $this->formatBytes($stats['total_size']));

                if ($stats['total_events'] > 0) {
                    $protobufRatio = ($stats['protobuf_events'] / $stats['total_events']) * 100;
                    $this->line("  Protobuf Usage: " . round($protobufRatio, 1) . "%");
                }
            } else {
                $this->comment("Event Store: Standard (non-Protobuf)");
            }
        } catch (\Throwable $e) {
            $this->error("Failed to get EventStore stats: " . $e->getMessage());
        }
    }

    /**
     * Format bytes to human-readable.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }
}
