<?php

namespace Core\Streaming;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Stream\Stream;
use Psr\Log\LoggerInterface;

/**
 * NATS JetStream Connection Manager.
 * 
 * Manages connection to NATS server and JetStream context.
 */
class NatsConnection
{
    private ?Client $client = null;
    private array $streams = [];

    public function __construct(
        private string $host,
        private int $port,
        private LoggerInterface $logger,
        private ?string $user = null,
        private ?string $password = null,
    ) {
    }

    /**
     * Get NATS client.
     */
    public function getClient(): Client
    {
        if ($this->client === null) {
            $this->connect();
        }

        return $this->client;
    }

    /**
     * Connect to NATS server.
     */
    protected function connect(): void
    {
        $this->logger->info("Connecting to NATS", [
            'host' => $this->host,
            'port' => $this->port,
        ]);

        $config = new Configuration([
            'host' => $this->host,
            'port' => $this->port,
            'user' => $this->user,
            'pass' => $this->password,
            'pedantic' => false,
            'reconnect' => true,
            'timeout' => 1,
        ]);

        $this->client = new Client($config);
        $this->client->connect();

        $this->logger->info("Connected to NATS successfully");
    }

    /**
     * Get or create stream.
     */
    public function getStream(string $name): Stream
    {
        if (!isset($this->streams[$name])) {
            $this->streams[$name] = $this->getClient()->getApi()->getStream($name);
        }

        return $this->streams[$name];
    }

    /**
     * Create stream with configuration.
     */
    public function createStream(string $name, array $config): Stream
    {
        $this->logger->info("Creating NATS stream", [
            'name' => $name,
            'config' => $config,
        ]);

        $api = $this->getClient()->getApi();
        
        $stream = $api->getStream($name);
        
        // Configure stream
        $stream->setSubjects($config['subjects'] ?? []);
        
        if (isset($config['retention'])) {
            $stream->setRetention($config['retention']);
        }
        
        if (isset($config['max_age'])) {
            $stream->setMaxAge($config['max_age']);
        }
        
        if (isset($config['max_msgs'])) {
            $stream->setMaxMsgs($config['max_msgs']);
        }
        
        if (isset($config['max_bytes'])) {
            $stream->setMaxBytes($config['max_bytes']);
        }

        $stream->create();

        $this->streams[$name] = $stream;

        $this->logger->info("Stream created successfully", ['name' => $name]);

        return $stream;
    }

    /**
     * Publish message to subject.
     */
    public function publish(string $subject, string $data, array $headers = []): void
    {
        $this->getClient()->publish($subject, $data, headers: $headers);
    }

    /**
     * Subscribe to subject.
     */
    public function subscribe(string $subject, callable $callback): void
    {
        $this->getClient()->subscribe($subject, $callback);
    }

    /**
     * Close connection.
     */
    public function close(): void
    {
        if ($this->client !== null) {
            $this->logger->info("Closing NATS connection");
            $this->client->disconnect();
            $this->client = null;
        }
    }

    /**
     * Check if connected.
     */
    public function isConnected(): bool
    {
        return $this->client !== null;
    }

    /**
     * Ping server.
     */
    public function ping(): bool
    {
        try {
            $this->getClient()->ping();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("NATS ping failed", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
