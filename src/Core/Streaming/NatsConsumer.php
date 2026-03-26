<?php

namespace Core\Streaming;

use Core\Events\Event;
use Psr\Log\LoggerInterface;

/**
 * NATS JetStream Consumer.
 * 
 * Consumes events from NATS stream with:
 * - Durable subscriptions
 * - Automatic acknowledgment
 * - Error handling & retry
 * - Consumer groups
 */
class NatsConsumer
{
    private bool $running = false;

    public function __construct(
        private NatsConnection $connection,
        private EventSerializer $serializer,
        private LoggerInterface $logger,
        private string $streamName = 'EVENTS',
        private string $consumerName = 'default-consumer',
    ) {
    }

    /**
     * Consume events from stream.
     */
    public function consume(array $subjects, callable $handler, array $options = []): void
    {
        $this->running = true;

        $this->logger->info("Starting NATS consumer", [
            'stream' => $this->streamName,
            'consumer' => $this->consumerName,
            'subjects' => $subjects,
        ]);

        try {
            $stream = $this->connection->getStream($this->streamName);
            
            // Create or get consumer
            $consumer = $stream->getConsumer($this->consumerName);
            
            if (!$consumer->exists()) {
                $consumer->create([
                    'durable_name' => $this->consumerName,
                    'filter_subject' => implode(',', $subjects),
                    'ack_policy' => 'explicit',
                    'max_deliver' => $options['max_retries'] ?? 3,
                    'ack_wait' => ($options['ack_timeout'] ?? 30) * 1_000_000_000, // nanoseconds
                ]);
            }

            // Process messages
            while ($this->running) {
                $messages = $consumer->fetch($options['batch_size'] ?? 10);

                foreach ($messages as $message) {
                    try {
                        // Deserialize event
                        $event = $this->serializer->deserialize($message->payload);

                        $this->logger->debug("Processing event from NATS", [
                            'event' => get_class($event),
                            'subject' => $message->subject,
                        ]);

                        // Handle event
                        $handler($event, $message);

                        // Acknowledge
                        $message->ack();

                        $this->logger->debug("Event processed successfully", [
                            'event' => get_class($event),
                        ]);
                    } catch (\Throwable $e) {
                        $this->logger->error("Failed to process event", [
                            'subject' => $message->subject,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        // Negative acknowledgment (will retry)
                        $message->nak();
                    }
                }

                // Wait before next fetch
                usleep(($options['poll_interval'] ?? 100) * 1000);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Consumer error", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Stop consuming.
     */
    public function stop(): void
    {
        $this->running = false;
        $this->logger->info("Stopping NATS consumer");
    }

    /**
     * Check if running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Get consumer name.
     */
    public function getConsumerName(): string
    {
        return $this->consumerName;
    }

    /**
     * Get stream name.
     */
    public function getStreamName(): string
    {
        return $this->streamName;
    }
}
