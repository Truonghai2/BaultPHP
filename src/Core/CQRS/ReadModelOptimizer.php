<?php

declare(strict_types=1);

namespace Core\CQRS;

use Core\Support\Facades\Log;
use PDO;

/**
 * Read Model Optimizer
 *
 * Optimizes CQRS read models through:
 * - Auto-generating optimized read models
 * - Materialized views
 * - Denormalization strategies
 * - Projection optimization
 *
 * Features:
 * - Automatic denormalization
 * - Materialized view generation
 * - Index optimization
 * - Query pattern analysis
 */
class ReadModelOptimizer
{
    protected PDO $connection;
    protected array $config = [];
    protected array $projections = [];
    protected array $queryPatterns = [];

    public function __construct(
        PDO $connection,
        array $config = [],
    ) {
        $this->connection = $connection;
        $this->config = array_merge([
            'enabled' => env('READ_MODEL_OPTIMIZATION_ENABLED', false),
            'auto_denormalize' => env('READ_MODEL_AUTO_DENORMALIZE', true),
            'materialized_views' => env('READ_MODEL_MATERIALIZED_VIEWS', true),
            'index_optimization' => env('READ_MODEL_INDEX_OPTIMIZATION', true),
            'analyze_queries' => env('READ_MODEL_ANALYZE_QUERIES', true),
        ], $config);
    }

    /**
     * Optimize all projections
     */
    public function optimizeProjections(): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        Log::info("Starting read model optimization");

        // Analyze query patterns
        if ($this->config['analyze_queries']) {
            $this->analyzeQueryPatterns();
        }

        // Optimize each projection
        foreach ($this->projections as $projectionName => $projection) {
            $this->optimizeProjection($projectionName, $projection);
        }

        // Create materialized views
        if ($this->config['materialized_views']) {
            $this->createMaterializedViews();
        }

        // Optimize indexes
        if ($this->config['index_optimization']) {
            $this->optimizeIndexes();
        }

        Log::info("Read model optimization completed");
    }

    /**
     * Register a projection for optimization
     */
    public function registerProjection(string $name, array $config): void
    {
        $this->projections[$name] = array_merge([
            'table' => null,
            'source_tables' => [],
            'denormalize_fields' => [],
            'indexes' => [],
            'materialized' => false,
        ], $config);
    }

    /**
     * Optimize a specific projection
     */
    protected function optimizeProjection(string $name, array $config): void
    {
        Log::debug("Optimizing projection", ['projection' => $name]);

        // Auto-denormalize if enabled
        if ($this->config['auto_denormalize'] && !empty($config['denormalize_fields'])) {
            $this->denormalizeProjection($name, $config);
        }

        // Create indexes
        if (!empty($config['indexes'])) {
            $this->createIndexes($name, $config);
        }
    }

    /**
     * Denormalize projection fields
     */
    protected function denormalizeProjection(string $name, array $config): void
    {
        $table = $config['table'];
        if (!$table) {
            return;
        }

        foreach ($config['denormalize_fields'] as $field => $source) {
            $this->denormalizeField($table, $field, $source);
        }

        Log::info("Denormalized projection fields", [
            'projection' => $name,
            'fields' => array_keys($config['denormalize_fields']),
        ]);
    }

    /**
     * Denormalize a specific field
     */
    protected function denormalizeField(string $table, string $field, array $source): void
    {
        $sourceTable = $source['table'] ?? null;
        $sourceField = $source['field'] ?? null;
        $joinKey = $source['join_key'] ?? 'id';
        $targetKey = $source['target_key'] ?? $joinKey;

        if (!$sourceTable || !$sourceField) {
            return;
        }

        // Check if field exists
        if (!$this->columnExists($table, $field)) {
            $this->addColumn($table, $field, $this->getColumnType($sourceTable, $sourceField));
        }

        // Update denormalized data
        $sql = "
            UPDATE {$table} t
            INNER JOIN {$sourceTable} s ON t.{$targetKey} = s.{$joinKey}
            SET t.{$field} = s.{$sourceField}
        ";

        try {
            $this->connection->exec($sql);
            Log::debug("Denormalized field", [
                'table' => $table,
                'field' => $field,
                'source' => "{$sourceTable}.{$sourceField}",
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to denormalize field", [
                'table' => $table,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create materialized views
     */
    protected function createMaterializedViews(): void
    {
        foreach ($this->projections as $name => $config) {
            if ($config['materialized'] ?? false) {
                $this->createMaterializedView($name, $config);
            }
        }
    }

    /**
     * Create a materialized view
     */
    protected function createMaterializedView(string $name, array $config): void
    {
        $table = $config['table'] ?? null;
        if (!$table) {
            return;
        }

        $viewName = "mv_{$name}";
        $sql = $config['view_sql'] ?? "SELECT * FROM {$table}";

        // Check database type
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'pgsql') {
                // PostgreSQL materialized view
                $createSql = "CREATE MATERIALIZED VIEW IF NOT EXISTS {$viewName} AS {$sql}";
                $this->connection->exec($createSql);
                
                // Create indexes on materialized view
                if (!empty($config['indexes'])) {
                    foreach ($config['indexes'] as $index) {
                        $indexName = $index['name'] ?? "idx_{$viewName}_{$index['column']}";
                        $indexSql = "CREATE INDEX IF NOT EXISTS {$indexName} ON {$viewName} ({$index['column']})";
                        $this->connection->exec($indexSql);
                    }
                }
            } elseif ($driver === 'mysql') {
                // MySQL doesn't support materialized views natively
                // Create a regular table that acts as materialized view
                $createSql = "CREATE TABLE IF NOT EXISTS {$viewName} AS {$sql}";
                $this->connection->exec($createSql);
            }

            Log::info("Created materialized view", [
                'view' => $viewName,
                'projection' => $name,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to create materialized view", [
                'view' => $viewName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Refresh materialized view
     */
    public function refreshMaterializedView(string $name): void
    {
        $config = $this->projections[$name] ?? null;
        if (!$config || !($config['materialized'] ?? false)) {
            return;
        }

        $viewName = "mv_{$name}";
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'pgsql') {
                $this->connection->exec("REFRESH MATERIALIZED VIEW {$viewName}");
            } elseif ($driver === 'mysql') {
                // Drop and recreate
                $this->connection->exec("DROP TABLE IF EXISTS {$viewName}");
                $this->createMaterializedView($name, $config);
            }

            Log::info("Refreshed materialized view", ['view' => $viewName]);
        } catch (\Throwable $e) {
            Log::error("Failed to refresh materialized view", [
                'view' => $viewName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create indexes for projection
     */
    protected function createIndexes(string $name, array $config): void
    {
        $table = $config['table'] ?? null;
        if (!$table || empty($config['indexes'])) {
            return;
        }

        foreach ($config['indexes'] as $index) {
            $indexName = $index['name'] ?? "idx_{$table}_{$index['column']}";
            $columns = is_array($index['column']) 
                ? implode(', ', $index['column'])
                : $index['column'];
            
            $unique = $index['unique'] ?? false ? 'UNIQUE' : '';
            $sql = "CREATE {$unique} INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columns})";

            try {
                $this->connection->exec($sql);
                Log::debug("Created index", [
                    'table' => $table,
                    'index' => $indexName,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Failed to create index", [
                    'table' => $table,
                    'index' => $indexName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Optimize indexes based on query patterns
     */
    protected function optimizeIndexes(): void
    {
        if (empty($this->queryPatterns)) {
            return;
        }

        // Analyze query patterns and suggest indexes
        foreach ($this->queryPatterns as $table => $patterns) {
            $suggestedIndexes = $this->suggestIndexes($table, $patterns);
            
            foreach ($suggestedIndexes as $index) {
                $this->createIndexIfNotExists($table, $index);
            }
        }
    }

    /**
     * Analyze query patterns
     */
    protected function analyzeQueryPatterns(): void
    {
        // In production, this would analyze slow query logs or query statistics
        // For now, we'll use a placeholder that can be extended
        
        Log::debug("Analyzing query patterns");
        
        // Placeholder: Would integrate with query logging/monitoring
        // $this->queryPatterns = $this->fetchQueryPatterns();
    }

    /**
     * Suggest indexes based on query patterns
     */
    protected function suggestIndexes(string $table, array $patterns): array
    {
        $indexes = [];
        
        // Analyze WHERE clauses
        foreach ($patterns as $pattern) {
            if (!empty($pattern['where'])) {
                $columns = $pattern['where'];
                if (count($columns) > 0) {
                    $indexes[] = [
                        'name' => "idx_{$table}_" . implode('_', $columns),
                        'column' => $columns,
                    ];
                }
            }
        }
        
        return $indexes;
    }

    /**
     * Create index if not exists
     */
    protected function createIndexIfNotExists(string $table, array $index): void
    {
        $indexName = $index['name'];
        $columns = is_array($index['column']) 
            ? implode(', ', $index['column'])
            : $index['column'];
        
        $sql = "CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columns})";
        
        try {
            $this->connection->exec($sql);
        } catch (\Throwable $e) {
            Log::warning("Failed to create suggested index", [
                'table' => $table,
                'index' => $indexName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if column exists
     */
    protected function columnExists(string $table, string $column): bool
    {
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        try {
            if ($driver === 'pgsql') {
                $sql = "
                    SELECT COUNT(*) 
                    FROM information_schema.columns 
                    WHERE table_name = ? AND column_name = ?
                ";
            } else {
                $sql = "
                    SELECT COUNT(*) 
                    FROM information_schema.columns 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ? 
                    AND column_name = ?
                ";
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$table, $column]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Add column to table
     */
    protected function addColumn(string $table, string $column, string $type): void
    {
        $sql = "ALTER TABLE {$table} ADD COLUMN {$column} {$type}";
        
        try {
            $this->connection->exec($sql);
        } catch (\Throwable $e) {
            Log::error("Failed to add column", [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get column type from source table
     */
    protected function getColumnType(string $table, string $column): string
    {
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        try {
            if ($driver === 'pgsql') {
                $sql = "
                    SELECT data_type 
                    FROM information_schema.columns 
                    WHERE table_name = ? AND column_name = ?
                ";
            } else {
                $sql = "
                    SELECT COLUMN_TYPE 
                    FROM information_schema.columns 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ? 
                    AND column_name = ?
                ";
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$table, $column]);
            $type = $stmt->fetchColumn();
            
            return $type ?: 'VARCHAR(255)';
        } catch (\Throwable $e) {
            return 'VARCHAR(255)'; // Default fallback
        }
    }

    /**
     * Get optimization statistics
     */
    public function getStats(): array
    {
        return [
            'projections_registered' => count($this->projections),
            'materialized_views' => array_filter(
                $this->projections,
                fn($p) => $p['materialized'] ?? false
            ),
            'query_patterns_analyzed' => count($this->queryPatterns),
            'enabled' => $this->config['enabled'],
        ];
    }
}
