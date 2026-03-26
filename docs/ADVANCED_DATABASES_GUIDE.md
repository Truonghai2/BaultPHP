# Advanced Database Technologies Guide

## Tổng quan

Hệ thống Advanced Database Technologies đã được triển khai với 3 loại database:

1. **Vector Database** - Semantic search và AI embeddings
2. **Time-Series Database** - Metrics và analytics
3. **Graph Database** - Complex relationships và recommendations

## 1. Vector Database

### Cấu hình

Thêm vào `.env`:
```env
VECTOR_DB_ENABLED=true
VECTOR_DB_DRIVER=pgvector
VECTOR_DB_DIMENSION=1536
```

### Supported Drivers

- **Pinecone**: Cloud-native vector database
- **Weaviate**: Open-source vector search engine
- **Qdrant**: Vector similarity search engine
- **pgvector**: PostgreSQL extension (recommended for existing PostgreSQL setups)

### Sử dụng

```php
use Core\Database\VectorDatabase;

$vectorDb = app(VectorDatabase::class);

// Create index/table
$vectorDb->createIndex('documents', [
    'dimension' => 1536,
    'metadata_columns' => [
        'title' => 'VARCHAR(255)',
        'content' => 'TEXT',
    ],
]);

// Upsert vectors
$vectors = [
    [
        'id' => 'doc1',
        'vector' => [0.1, 0.2, 0.3, ...], // Embedding vector
        'metadata' => [
            'title' => 'Document 1',
            'content' => '...',
        ],
    ],
];
$vectorDb->upsert('documents', $vectors);

// Search similar vectors
$queryVector = [0.1, 0.2, 0.3, ...]; // Query embedding
$results = $vectorDb->search($queryVector, 10, [
    'index' => 'documents',
    'filter' => [
        'where' => ['category' => 'tech'],
    ],
]);
```

### Use Cases

- **Semantic Search**: Tìm kiếm theo ý nghĩa thay vì keywords
- **AI/ML Embeddings**: Lưu trữ embeddings từ AI models
- **Recommendation Systems**: Tìm items tương tự
- **Content Similarity**: Tìm content tương tự

## 2. Time-Series Database

### Cấu hình

Thêm vào `.env`:
```env
TIMESERIES_DB_ENABLED=true
TIMESERIES_DB_DRIVER=timescaledb
TIMESCALEDB_CHUNK_INTERVAL=1 day
```

### Supported Drivers

- **InfluxDB**: Purpose-built time-series database
- **TimescaleDB**: PostgreSQL extension for time-series

### Sử dụng

```php
use Core\Database\TimeSeriesDatabase;

$tsDb = app(TimeSeriesDatabase::class);

// Write metrics
$tsDb->write('cpu_usage', [
    'value' => 75.5,
    'cpu_core' => 0,
], [
    'host' => 'server1',
    'region' => 'us-east',
]);

// Query time-series data
$results = $tsDb->query('cpu_usage', [
    'start' => '-1h',
    'end' => 'now()',
    'where' => ['host' => 'server1'],
    'aggregate' => 'avg',
    'group_by' => 'cpu_core',
    'limit' => 100,
]);

// Get aggregated metrics
$avgMetrics = $tsDb->getMetrics('cpu_usage', 'avg', [
    'start' => '-24h',
    'group_by' => 'host',
]);
```

### Use Cases

- **Metrics Collection**: CPU, memory, disk usage
- **Analytics**: User activity, sales trends
- **Monitoring**: Application performance metrics
- **IoT Data**: Sensor readings, device telemetry

## 3. Graph Database

### Cấu hình

Thêm vào `.env`:
```env
GRAPH_DB_ENABLED=true
GRAPH_DB_DRIVER=neo4j
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=password
```

### Supported Drivers

- **Neo4j**: Native graph database
- **ArangoDB**: Multi-model database with graph support

### Sử dụng

```php
use Core\Database\GraphDatabase;

$graphDb = app(GraphDatabase::class);

// Create nodes
$userId = $graphDb->createNode('User', [
    'id' => 'user123',
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

$productId = $graphDb->createNode('Product', [
    'id' => 'prod456',
    'name' => 'Laptop',
    'price' => 999.99,
]);

// Create relationships
$graphDb->createRelationship(
    'User', 'user123',
    'Product', 'prod456',
    'PURCHASED',
    ['date' => '2024-01-15', 'quantity' => 1]
);

// Query graph
$cypher = "
    MATCH (u:User {id: 'user123'})-[:PURCHASED]->(p:Product)
    RETURN p
";
$purchases = $graphDb->query($cypher);

// Find shortest path
$path = $graphDb->shortestPath('User', 'user1', 'User', 'user2');

// Get recommendations
$recommendations = $graphDb->getRecommendations(
    'User', 'user123',
    'PURCHASED',
    10
);
```

### Use Cases

- **Social Networks**: Friend connections, followers
- **Recommendation Engines**: "Users who bought X also bought Y"
- **Knowledge Graphs**: Entity relationships
- **Complex Relationships**: Multi-hop queries

## Examples

### Example 1: Semantic Search với pgvector

```php
use Core\Database\VectorDatabase;

$vectorDb = app(VectorDatabase::class);

// Create table for document embeddings
$vectorDb->createIndex('documents', [
    'dimension' => 1536, // OpenAI ada-002 dimension
    'metadata_columns' => [
        'title' => 'VARCHAR(255)',
        'content' => 'TEXT',
        'category' => 'VARCHAR(50)',
    ],
]);

// Store document with embedding
$embedding = getOpenAIEmbedding($documentContent); // From OpenAI API
$vectorDb->upsert('documents', [[
    'id' => $documentId,
    'vector' => $embedding,
    'metadata' => [
        'title' => $documentTitle,
        'content' => $documentContent,
        'category' => 'tech',
    ],
]]);

// Search similar documents
$queryEmbedding = getOpenAIEmbedding($searchQuery);
$results = $vectorDb->search($queryEmbedding, 10, [
    'index' => 'documents',
]);
```

### Example 2: Metrics Collection với TimescaleDB

```php
use Core\Database\TimeSeriesDatabase;

$tsDb = app(TimeSeriesDatabase::class);

// Write application metrics
$tsDb->write('api_requests', [
    'response_time' => 150, // ms
    'status_code' => 200,
], [
    'endpoint' => '/api/users',
    'method' => 'GET',
]);

// Query average response time per endpoint
$avgResponseTime = $tsDb->getMetrics('api_requests', 'avg', [
    'start' => '-1h',
    'fields' => ['response_time'],
    'group_by' => 'endpoint',
]);
```

### Example 3: Social Network với Neo4j

```php
use Core\Database\GraphDatabase;

$graphDb = app(GraphDatabase::class);

// Create user nodes
$user1 = $graphDb->createNode('User', ['id' => 'user1', 'name' => 'Alice']);
$user2 = $graphDb->createNode('User', ['id' => 'user2', 'name' => 'Bob']);

// Create friendship
$graphDb->createRelationship('User', 'user1', 'User', 'user2', 'FRIENDS_WITH');

// Find mutual friends
$cypher = "
    MATCH (u1:User {id: 'user1'})-[:FRIENDS_WITH]-(mutual)-[:FRIENDS_WITH]-(u2:User {id: 'user2'})
    WHERE mutual.id <> 'user1' AND mutual.id <> 'user2'
    RETURN mutual
";
$mutualFriends = $graphDb->query($cypher);

// Get friend recommendations
$recommendations = $graphDb->getRecommendations('User', 'user1', 'FRIENDS_WITH', 10);
```

## Best Practices

### Vector Database

1. **Dimension Consistency**: Đảm bảo tất cả vectors có cùng dimension
2. **Index Type**: Chọn index type phù hợp (ivfflat cho speed, hnsw cho accuracy)
3. **Metadata**: Store metadata trong cùng table để filter dễ dàng
4. **Batch Upserts**: Upsert nhiều vectors cùng lúc để tối ưu performance

### Time-Series Database

1. **Chunk Interval**: Tune chunk interval dựa trên data volume
2. **Retention Policies**: Set retention policies để tự động xóa old data
3. **Compression**: Enable compression cho TimescaleDB
4. **Continuous Aggregates**: Use continuous aggregates cho common queries

### Graph Database

1. **Indexes**: Create indexes trên properties thường query
2. **Relationship Types**: Use consistent relationship type naming
3. **Path Depth**: Limit path depth trong queries để tránh performance issues
4. **Batch Operations**: Batch create nodes/relationships khi có thể

## Performance Tips

1. **Vector Search**: Use approximate nearest neighbor (ANN) indexes
2. **Time-Series**: Use downsampling cho long-term data
3. **Graph**: Limit relationship traversal depth
4. **Connection Pooling**: Reuse connections cho all database types

## Troubleshooting

### Vector Database

**Low search accuracy:**
- Increase index size (ivfflat lists)
- Use HNSW index type
- Check vector normalization

**Slow queries:**
- Create proper indexes
- Reduce vector dimension if possible
- Use approximate search

### Time-Series Database

**High storage usage:**
- Enable compression
- Set retention policies
- Use downsampling

**Slow queries:**
- Create indexes on tags/fields
- Use continuous aggregates
- Optimize chunk interval

### Graph Database

**Slow path queries:**
- Limit path depth
- Create indexes on node properties
- Use shortest path algorithms

**Memory issues:**
- Limit result set size
- Use pagination
- Optimize query patterns

## Kết luận

Advanced Database Technologies cung cấp:

- ✅ **Semantic search** với vector databases
- ✅ **Time-series analytics** với specialized databases
- ✅ **Complex relationships** với graph databases
- ✅ **Multi-driver support** cho flexibility
- ✅ **Easy integration** với existing codebase

Enable các databases theo nhu cầu và use cases cụ thể.
