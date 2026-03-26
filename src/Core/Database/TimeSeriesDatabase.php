<?php

declare(strict_types=1);

namespace Core\Database;

use Core\Support\Facades\Log;
use GuzzleHttp\Client;
use PDO;

/**
 * Time-Series Database
 *
 * Provides time-series data storage and querying capabilities.
 * Supports InfluxDB and TimescaleDB (PostgreSQL extension).
 *
 * Features:
 * - Metrics collection
 * - Analytics queries
 * - Monitoring data
 * - IoT data storage
 */
class TimeSeriesDatabase
{
    protected array $config = [];
    protected ?object $client = null;
    protected ?PDO $pdo = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => env('TIMESERIES_DB_DRIVER', 'timescaledb'),
            'enabled' => env('TIMESERIES_DB_ENABLED', false),
        ], $config);

        if ($this->config['enabled']) {
            $this->initializeClient();
        }
    }

    /**
     * Initialize time-series database client
     */
    protected function initializeClient(): void
    {
        $driver = $this->config['driver'];

        try {
            match ($driver) {
                'influxdb' => $this->initializeInfluxDB(),
                'timescaledb' => $this->initializeTimescaleDB(),
                default => throw new \InvalidArgumentException("Unsupported time-series driver: {$driver}"),
            };
        } catch (\Throwable $e) {
            Log::error("Failed to initialize time-series database", [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Initialize InfluxDB client
     */
    protected function initializeInfluxDB(): void
    {
        $url = $this->config['influxdb']['url'] ?? env('INFLUXDB_URL', 'http://localhost:8086');
        $token = $this->config['influxdb']['token'] ?? env('INFLUXDB_TOKEN');
        $org = $this->config['influxdb']['org'] ?? env('INFLUXDB_ORG', 'my-org');
        $bucket = $this->config['influxdb']['bucket'] ?? env('INFLUXDB_BUCKET', 'metrics');

        $this->client = new class($url, $token, $org, $bucket) {
            public function __construct(
                protected string $url,
                protected ?string $token,
                protected string $org,
                protected string $bucket,
            ) {}

            public function write(string $measurement, array $fields, array $tags = [], ?int $timestamp = null): void
            {
                // Placeholder: Would use InfluxDB PHP client
                // $writeApi = $client->createWriteApi();
                // $point = Point::measurement($measurement)
                //     ->addTag('host', $tags['host'] ?? 'server1')
                //     ->addField('value', $fields['value'])
                //     ->time($timestamp ?? time());
                // $writeApi->write($point);
                
                Log::debug("InfluxDB write (placeholder)", [
                    'measurement' => $measurement,
                    'fields' => $fields,
                ]);
            }

            public function query(string $fluxQuery): array
            {
                // Placeholder: Would use InfluxDB PHP client
                Log::debug("InfluxDB query (placeholder)", [
                    'query' => substr($fluxQuery, 0, 100),
                ]);
                
                return [];
            }
        };
    }

    /**
     * Initialize TimescaleDB (PostgreSQL extension)
     */
    protected function initializeTimescaleDB(): void
    {
        // Use existing PDO connection
        $this->pdo = app(\PDO::class);
        
        // Ensure TimescaleDB extension is enabled
        $this->ensureTimescaleDBExtension();
    }

    /**
     * Ensure TimescaleDB extension is enabled
     */
    protected function ensureTimescaleDBExtension(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM pg_extension WHERE extname = 'timescaledb'");
            if ($stmt->rowCount() === 0) {
                $this->pdo->exec("CREATE EXTENSION IF NOT EXISTS timescaledb");
                Log::info("TimescaleDB extension enabled");
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to enable TimescaleDB extension", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Write a data point
     *
     * @param string $measurement Measurement/table name
     * @param array $fields Field values
     * @param array $tags Tag values (for InfluxDB)
     * @param int|null $timestamp Unix timestamp (null for now)
     */
    public function write(string $measurement, array $fields, array $tags = [], ?int $timestamp = null): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        $driver = $this->config['driver'];
        $timestamp = $timestamp ?? time();

        match ($driver) {
            'influxdb' => $this->client->write($measurement, $fields, $tags, $timestamp),
            'timescaledb' => $this->writeTimescaleDB($measurement, $fields, $tags, $timestamp),
            default => null,
        };
    }

    /**
     * Write to TimescaleDB
     */
    protected function writeTimescaleDB(string $table, array $fields, array $tags, int $timestamp): void
    {
        if (!$this->pdo) {
            return;
        }

        try {
            // Ensure table exists as hypertable
            $this->ensureHypertable($table, array_merge($fields, $tags));

            // Build INSERT query
            $columns = ['time'];
            $placeholders = [':time'];
            $values = [':time' => date('Y-m-d H:i:s', $timestamp)];

            foreach ($fields as $key => $value) {
                $columns[] = $key;
                $placeholders[] = ":{$key}";
                $values[":{$key}"] = $value;
            }

            foreach ($tags as $key => $value) {
                $columns[] = $key;
                $placeholders[] = ":{$key}";
                $values[":{$key}"] = $value;
            }

            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
        } catch (\Throwable $e) {
            Log::error("TimescaleDB write failed", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Query time-series data
     *
     * @param string $measurement Measurement/table name
     * @param array $options Query options
     * @return array Query results
     */
    public function query(string $measurement, array $options = []): array
    {
        if (!$this->config['enabled']) {
            return [];
        }

        $driver = $this->config['driver'];

        return match ($driver) {
            'influxdb' => $this->queryInfluxDB($measurement, $options),
            'timescaledb' => $this->queryTimescaleDB($measurement, $options),
            default => [],
        };
    }

    /**
     * Query InfluxDB
     */
    protected function queryInfluxDB(string $measurement, array $options): array
    {
        $start = $options['start'] ?? '-1h';
        $stop = $options['stop'] ?? 'now()';
        $fields = $options['fields'] ?? ['*'];
        $where = $options['where'] ?? [];

        $fluxQuery = "from(bucket: \"{$this->client->bucket}\")
            |> range(start: {$start}, stop: {$stop})
            |> filter(fn: (r) => r._measurement == \"{$measurement}\")";

        if (!empty($where)) {
            foreach ($where as $key => $value) {
                $fluxQuery .= "|> filter(fn: (r) => r.{$key} == \"{$value}\")";
            }
        }

        return $this->client->query($fluxQuery);
    }

    /**
     * Query TimescaleDB
     */
    protected function queryTimescaleDB(string $table, array $options): array
    {
        if (!$this->pdo) {
            return [];
        }

        try {
            $start = $options['start'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
            $end = $options['end'] ?? date('Y-m-d H:i:s');
            $fields = $options['fields'] ?? ['*'];
            $where = $options['where'] ?? [];
            $groupBy = $options['group_by'] ?? null;
            $aggregate = $options['aggregate'] ?? null;

            $selectFields = is_array($fields) ? implode(', ', $fields) : $fields;

            $sql = "SELECT {$selectFields} FROM {$table} WHERE time >= :start AND time <= :end";

            $values = [
                ':start' => $start,
                ':end' => $end,
            ];

            foreach ($where as $key => $value) {
                $sql .= " AND {$key} = :{$key}";
                $values[":{$key}"] = $value;
            }

            if ($groupBy && $aggregate) {
                $sql .= " GROUP BY {$groupBy}";
                if ($aggregate === 'avg') {
                    $sql = str_replace($selectFields, "AVG({$selectFields}) as avg_value", $sql);
                } elseif ($aggregate === 'sum') {
                    $sql = str_replace($selectFields, "SUM({$selectFields}) as sum_value", $sql);
                }
            }

            $sql .= " ORDER BY time DESC";

            if (isset($options['limit'])) {
                $sql .= " LIMIT " . (int) $options['limit'];
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            Log::error("TimescaleDB query failed", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Ensure table exists as hypertable
     */
    protected function ensureHypertable(string $table, array $columns): void
    {
        if (!$this->pdo) {
            return;
        }

        try {
            // Check if table exists
            $stmt = $this->pdo->query("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_name = '{$table}'
                )
            ");
            
            if (!$stmt->fetchColumn()) {
                // Create table
                $columnDefs = ['time TIMESTAMPTZ NOT NULL'];
                
                foreach ($columns as $column => $value) {
                    $type = $this->inferColumnType($value);
                    $columnDefs[] = "{$column} {$type}";
                }

                $sql = "CREATE TABLE {$table} (" . implode(', ', $columnDefs) . ")";
                $this->pdo->exec($sql);

                // Convert to hypertable
                $chunkInterval = $this->config['timescaledb']['chunk_interval'] ?? '1 day';
                $this->pdo->exec("SELECT create_hypertable('{$table}', 'time', chunk_time_interval => INTERVAL '{$chunkInterval}')");

                Log::info("Created TimescaleDB hypertable", [
                    'table' => $table,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to create hypertable", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Infer column type from value
     */
    protected function inferColumnType(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'BIGINT',
            is_float($value) => 'DOUBLE PRECISION',
            is_bool($value) => 'BOOLEAN',
            default => 'TEXT',
        };
    }

    /**
     * Get aggregated metrics
     */
    public function getMetrics(string $measurement, string $aggregate, array $options = []): array
    {
        $options['aggregate'] = $aggregate;
        return $this->query($measurement, $options);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'driver' => $this->config['driver'],
            'enabled' => $this->config['enabled'],
        ];
    }
}
