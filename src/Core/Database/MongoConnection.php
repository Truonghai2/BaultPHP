<?php

namespace Core\Database;

use MongoDB\Client;
use MongoDB\Database;

class MongoConnection
{
    protected Client $client;
    protected Database $database;
    protected string $databaseName;

    public function __construct(array $config)
    {
        $this->databaseName = $config['database'];
        
        $uri = $this->buildUri($config);
        $options = $config['options'] ?? [];
        $driverOptions = $config['driver_options'] ?? [];

        $this->client = new Client($uri, $options, $driverOptions);
        $this->database = $this->client->selectDatabase($this->databaseName);
    }

    protected function buildUri(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 27017;
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        
        $auth = '';
        if ($username && $password) {
            $auth = "{$username}:{$password}@";
        }

        return "mongodb://{$auth}{$host}:{$port}/{$this->databaseName}";
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function collection(string $name): \MongoDB\Collection
    {
        return $this->database->selectCollection($name);
    }
}
