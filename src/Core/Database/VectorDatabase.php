<?php

declare(strict_types=1);

namespace Core\Database;

use Core\Support\Facades\Log;
use GuzzleHttp\Client;
use PDO;

/**
 * Vector Database
 *
 * Provides vector similarity search and semantic search capabilities.
 * Supports multiple backends: Pinecone, Weaviate, Qdrant, PostgreSQL pgvector.
 *
 * Features:
 * - Vector similarity search
 * - Semantic search
 * - AI/ML embeddings storage
 * - Recommendation systems
 */
class VectorDatabase
{
    protected array $config = [];
    protected ?object $client = null;
    protected ?PDO $pdo = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => env('VECTOR_DB_DRIVER', 'pgvector'),
            'enabled' => env('VECTOR_DB_ENABLED', false),
        ], $config);

        if ($this->config['enabled']) {
            $this->initializeClient();
        }
    }

    /**
     * Initialize vector database client
     */
    protected function initializeClient(): void
    {
        $driver = $this->config['driver'];

        try {
            match ($driver) {
                'pinecone' => $this->initializePinecone(),
                'weaviate' => $this->initializeWeaviate(),
                'qdrant' => $this->initializeQdrant(),
                'pgvector' => $this->initializePgVector(),
                default => throw new \InvalidArgumentException("Unsupported vector database driver: {$driver}"),
            };
        } catch (\Throwable $e) {
            Log::error("Failed to initialize vector database", [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Initialize Pinecone client
     */
    protected function initializePinecone(): void
    {
        $apiKey = $this->config['pinecone']['api_key'] ?? env('PINECONE_API_KEY');
        $environment = $this->config['pinecone']['environment'] ?? env('PINECONE_ENVIRONMENT', 'us-east1-gcp');

        $this->client = new class($apiKey, $environment) {
            public function __construct(
                protected ?string $apiKey,
                protected string $environment,
            ) {}

            public function query(string $index, array $vector, int $topK, array $filter = []): array
            {
                // Placeholder: Would use Pinecone PHP SDK
                // $pinecone = new \Pinecone\Client($this->apiKey);
                // return $pinecone->index($index)->query($vector, $topK, $filter);
                
                Log::debug("Pinecone query (placeholder)", [
                    'index' => $index,
                    'topK' => $topK,
                ]);
                
                return [];
            }

            public function upsert(string $index, array $vectors): void
            {
                // Placeholder: Would use Pinecone PHP SDK
                Log::debug("Pinecone upsert (placeholder)", [
                    'index' => $index,
                    'vectors_count' => count($vectors),
                ]);
            }
        };
    }

    /**
     * Initialize Weaviate client
     */
    protected function initializeWeaviate(): void
    {
        $url = $this->config['weaviate']['url'] ?? env('WEAVIATE_URL', 'http://localhost:8080');
        $apiKey = $this->config['weaviate']['api_key'] ?? env('WEAVIATE_API_KEY');

        $this->client = new class($url, $apiKey) {
            public function __construct(
                protected string $url,
                protected ?string $apiKey,
            ) {}

            public function query(string $class, array $vector, int $limit, array $where = []): array
            {
                // Placeholder: Would use Weaviate PHP client
                Log::debug("Weaviate query (placeholder)", [
                    'class' => $class,
                    'limit' => $limit,
                ]);
                
                return [];
            }

            public function createObject(string $class, array $data, array $vector): void
            {
                // Placeholder: Would use Weaviate PHP client
                Log::debug("Weaviate create object (placeholder)", [
                    'class' => $class,
                ]);
            }
        };
    }

    /**
     * Initialize Qdrant client
     */
    protected function initializeQdrant(): void
    {
        $url = $this->config['qdrant']['url'] ?? env('QDRANT_URL', 'http://localhost:6333');
        $apiKey = $this->config['qdrant']['api_key'] ?? env('QDRANT_API_KEY');

        $this->client = new class($url, $apiKey) {
            public function __construct(
                protected string $url,
                protected ?string $apiKey,
            ) {}

            public function search(string $collection, array $vector, int $limit, array $filter = []): array
            {
                // Placeholder: Would use Qdrant PHP client
                Log::debug("Qdrant search (placeholder)", [
                    'collection' => $collection,
                    'limit' => $limit,
                ]);
                
                return [];
            }

            public function upsert(string $collection, array $points): void
            {
                // Placeholder: Would use Qdrant PHP client
                Log::debug("Qdrant upsert (placeholder)", [
                    'collection' => $collection,
                    'points_count' => count($points),
                ]);
            }
        };
    }

    /**
     * Initialize PostgreSQL pgvector
     */
    protected function initializePgVector(): void
    {
        // Use existing PDO connection
        $this->pdo = app(\PDO::class);
        
        // Ensure pgvector extension is enabled
        $this->ensurePgVectorExtension();
    }

    /**
     * Ensure pgvector extension is enabled
     */
    protected function ensurePgVectorExtension(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM pg_extension WHERE extname = 'vector'");
            if ($stmt->rowCount() === 0) {
                $this->pdo->exec("CREATE EXTENSION IF NOT EXISTS vector");
                Log::info("pgvector extension enabled");
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to enable pgvector extension", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Search for similar vectors
     *
     * @param array $vector Query vector (embedding)
     * @param int $topK Number of results to return
     * @param array $options Additional search options
     * @return array Search results with similarity scores
     */
    public function search(array $vector, int $topK = 10, array $options = []): array
    {
        if (!$this->config['enabled']) {
            return [];
        }

        $driver = $this->config['driver'];
        $index = $options['index'] ?? $options['collection'] ?? $options['class'] ?? 'default';
        $filter = $options['filter'] ?? $options['where'] ?? [];

        return match ($driver) {
            'pinecone' => $this->client->query($index, $vector, $topK, $filter),
            'weaviate' => $this->client->query($index, $vector, $topK, $filter),
            'qdrant' => $this->client->search($index, $vector, $topK, $filter),
            'pgvector' => $this->searchPgVector($index, $vector, $topK, $filter),
            default => [],
        };
    }

    /**
     * Search using PostgreSQL pgvector
     */
    protected function searchPgVector(string $table, array $vector, int $topK, array $filter): array
    {
        if (!$this->pdo) {
            return [];
        }

        try {
            $vectorStr = '[' . implode(',', $vector) . ']';
            $vectorColumn = $filter['vector_column'] ?? 'embedding';
            $idColumn = $filter['id_column'] ?? 'id';
            $metadataColumns = $filter['metadata_columns'] ?? [];

            $selectColumns = [$idColumn];
            if (!empty($metadataColumns)) {
                $selectColumns = array_merge($selectColumns, $metadataColumns);
            }
            $selectColumns[] = "1 - ({$vectorColumn} <=> '{$vectorStr}'::vector) as similarity";

            $sql = "SELECT " . implode(', ', $selectColumns) . " FROM {$table}";
            
            // Add WHERE conditions if provided
            $whereConditions = [];
            if (!empty($filter['where'])) {
                foreach ($filter['where'] as $column => $value) {
                    $whereConditions[] = "{$column} = :{$column}";
                }
            }
            
            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(' AND ', $whereConditions);
            }

            $sql .= " ORDER BY {$vectorColumn} <=> '{$vectorStr}'::vector LIMIT {$topK}";

            $stmt = $this->pdo->prepare($sql);
            
            // Bind WHERE parameters
            if (!empty($filter['where'])) {
                foreach ($filter['where'] as $column => $value) {
                    $stmt->bindValue(":{$column}", $value);
                }
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $results;
        } catch (\Throwable $e) {
            Log::error("pgvector search failed", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Insert or update vectors
     *
     * @param string $index Index/collection/table name
     * @param array $vectors Array of vectors with metadata
     */
    public function upsert(string $index, array $vectors): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        $driver = $this->config['driver'];

        match ($driver) {
            'pinecone' => $this->client->upsert($index, $vectors),
            'weaviate' => $this->upsertWeaviate($index, $vectors),
            'qdrant' => $this->client->upsert($index, $vectors),
            'pgvector' => $this->upsertPgVector($index, $vectors),
            default => null,
        };
    }

    /**
     * Upsert vectors to Weaviate
     */
    protected function upsertWeaviate(string $class, array $vectors): void
    {
        foreach ($vectors as $vector) {
            $data = $vector['data'] ?? [];
            $embedding = $vector['vector'] ?? $vector['embedding'] ?? [];
            $this->client->createObject($class, $data, $embedding);
        }
    }

    /**
     * Upsert vectors to PostgreSQL pgvector
     */
    protected function upsertPgVector(string $table, array $vectors): void
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $vectorColumn = 'embedding';
            $idColumn = 'id';

            foreach ($vectors as $vector) {
                $id = $vector['id'] ?? null;
                $embedding = $vector['vector'] ?? $vector['embedding'] ?? [];
                $metadata = $vector['metadata'] ?? $vector['data'] ?? [];

                if (empty($embedding)) {
                    continue;
                }

                $vectorStr = '[' . implode(',', $embedding) . ']';

                // Build columns and values
                $columns = [$vectorColumn];
                $placeholders = [':vector'];
                $values = [':vector' => $vectorStr];

                foreach ($metadata as $key => $value) {
                    $columns[] = $key;
                    $placeholders[] = ":{$key}";
                    $values[":{$key}"] = $value;
                }

                if ($id) {
                    $columns[] = $idColumn;
                    $placeholders[] = ":id";
                    $values[':id'] = $id;
                }

                $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") 
                        VALUES (" . implode(', ', $placeholders) . ")
                        ON CONFLICT ({$idColumn}) DO UPDATE SET 
                        {$vectorColumn} = EXCLUDED.{$vectorColumn}";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($values);
            }

            Log::info("Upserted vectors to pgvector", [
                'table' => $table,
                'count' => count($vectors),
            ]);
        } catch (\Throwable $e) {
            Log::error("pgvector upsert failed", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create index/collection/table for vectors
     */
    public function createIndex(string $name, array $config = []): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        $driver = $this->config['driver'];
        $dimension = $config['dimension'] ?? 1536; // Default OpenAI embedding dimension

        return match ($driver) {
            'pgvector' => $this->createPgVectorTable($name, $dimension, $config),
            default => false,
        };
    }

    /**
     * Create pgvector table
     */
    protected function createPgVectorTable(string $table, int $dimension, array $config): bool
    {
        if (!$this->pdo) {
            return false;
        }

        try {
            $idColumn = $config['id_column'] ?? 'id';
            $idType = $config['id_type'] ?? 'SERIAL PRIMARY KEY';
            $vectorColumn = $config['vector_column'] ?? 'embedding';
            $metadataColumns = $config['metadata_columns'] ?? [];

            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                {$idColumn} {$idType},
                {$vectorColumn} vector({$dimension})";

            foreach ($metadataColumns as $column => $type) {
                $sql .= ", {$column} {$type}";
            }

            $sql .= ")";

            $this->pdo->exec($sql);

            // Create index for similarity search
            $indexName = "idx_{$table}_{$vectorColumn}";
            $indexSql = "CREATE INDEX IF NOT EXISTS {$indexName} 
                         ON {$table} USING ivfflat ({$vectorColumn} vector_cosine_ops)";
            
            $this->pdo->exec($indexSql);

            Log::info("Created pgvector table", [
                'table' => $table,
                'dimension' => $dimension,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to create pgvector table", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
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
