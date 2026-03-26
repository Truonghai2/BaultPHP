# Event Streaming & Read Model Optimization Guide

## Tổng quan

Hệ thống Event-Driven Architecture đã được nâng cấp với:

1. **StreamingEventBus** - Event streaming với Kafka/Pulsar/NATS
2. **ReadModelOptimizer** - Tối ưu CQRS read models

## 1. Event Streaming

### Cấu hình

Thêm vào `.env`:
```env
EVENT_STREAMING_ENABLED=true
EVENT_STREAMING_DRIVER=kafka
EVENT_STREAMING_BROKERS=localhost:9092
EVENT_STREAMING_TOPIC_PREFIX=events
EVENT_STREAMING_HISTORY=true
EVENT_STREAMING_MAX_HISTORY=1000
```

### Supported Drivers

- **Kafka**: Apache Kafka
- **Pulsar**: Apache Pulsar
- **NATS**: NATS Streaming
- **Redis**: Redis pub/sub (lightweight alternative)

### Sử dụng

```php
use Core\Events\EventDispatcherInterface;

// Events tự động được stream khi dispatch
$dispatcher = app(EventDispatcherInterface::class);
$dispatcher->dispatch(new UserCreated($userId, $email));
```

### Event Replay

```php
use Core\Events\StreamingEventBus;

$streamingBus = app(StreamingEventBus::class);

// Replay events from a time range
$from = new \DateTime('2024-01-01');
$to = new \DateTime('2024-01-31');
$streamingBus->replayEvents($from, $to);

// Replay events by type
$streamingBus->replayEventsByType(UserCreated::class, 100);

// Get history statistics
$stats = $streamingBus->getHistoryStats();
```

### Topic Naming

Events tự động được map thành topics:
- `Modules\User\Events\UserCreated` → `events.user.created`
- `Modules\Cms\Events\PagePublished` → `events.page.published`

## 2. Read Model Optimization

### Cấu hình

Thêm vào `.env`:
```env
READ_MODEL_OPTIMIZATION_ENABLED=true
READ_MODEL_AUTO_DENORMALIZE=true
READ_MODEL_MATERIALIZED_VIEWS=true
READ_MODEL_INDEX_OPTIMIZATION=true
```

### Đăng ký Projection

Trong `config/read-model-optimization.php`:

```php
'projections' => [
    'page_list' => [
        'table' => 'page_list_items',
        'source_tables' => ['pages', 'users'],
        'denormalize_fields' => [
            'author_name' => [
                'table' => 'users',
                'field' => 'name',
                'join_key' => 'id',
                'target_key' => 'author_id',
            ],
        ],
        'indexes' => [
            ['name' => 'idx_status', 'column' => 'status'],
            ['name' => 'idx_author', 'column' => 'author_id'],
            ['name' => 'idx_composite', 'column' => ['status', 'author_id']],
        ],
        'materialized' => true,
        'view_sql' => 'SELECT * FROM page_list_items WHERE status = "published"',
    ],
],
```

### Sử dụng

```php
use Core\CQRS\ReadModelOptimizer;

$optimizer = app(ReadModelOptimizer::class);

// Optimize all projections
$optimizer->optimizeProjections();

// Refresh materialized view
$optimizer->refreshMaterializedView('page_list');

// Get statistics
$stats = $optimizer->getStats();
```

### Auto-Denormalization

Tự động denormalize fields từ related tables:

```php
'denormalize_fields' => [
    'author_name' => [
        'table' => 'users',
        'field' => 'name',
        'join_key' => 'id',
        'target_key' => 'author_id',
    ],
    'category_name' => [
        'table' => 'categories',
        'field' => 'name',
        'join_key' => 'id',
        'target_key' => 'category_id',
    ],
],
```

### Materialized Views

Tự động tạo materialized views cho optimized queries:

```php
'materialized' => true,
'view_sql' => 'SELECT * FROM page_list_items WHERE status = "published"',
```

**PostgreSQL**: Tạo materialized view thực sự
**MySQL**: Tạo table thay thế (MySQL không hỗ trợ materialized views)

### Index Optimization

Tự động tạo indexes dựa trên:
- Query patterns analysis
- Denormalized fields
- Config-defined indexes

## Best Practices

### Event Streaming

1. **Topic Naming**: Sử dụng consistent naming convention
2. **Event Versioning**: Include version trong event payload
3. **Error Handling**: Handle streaming failures gracefully
4. **Monitoring**: Monitor event throughput và latency

### Read Model Optimization

1. **Denormalization**: Chỉ denormalize fields được query thường xuyên
2. **Materialized Views**: Refresh định kỳ hoặc on-demand
3. **Indexes**: Không tạo quá nhiều indexes (impact write performance)
4. **Query Analysis**: Monitor query patterns để optimize

## Examples

### Example 1: Event Streaming với Kafka

```php
// .env
EVENT_STREAMING_ENABLED=true
EVENT_STREAMING_DRIVER=kafka
KAFKA_BROKERS=localhost:9092

// Dispatch event
event(new UserCreated($userId, $email));
// Event tự động được stream đến Kafka topic: events.user.created
```

### Example 2: Read Model Optimization

```php
// Register projection
$optimizer = app(ReadModelOptimizer::class);
$optimizer->registerProjection('user_list', [
    'table' => 'user_list_items',
    'denormalize_fields' => [
        'role_name' => [
            'table' => 'roles',
            'field' => 'name',
            'join_key' => 'id',
            'target_key' => 'role_id',
        ],
    ],
    'indexes' => [
        ['name' => 'idx_role', 'column' => 'role_id'],
    ],
]);

// Optimize
$optimizer->optimizeProjections();
```

### Example 3: Event Replay cho Analytics

```php
$streamingBus = app(StreamingEventBus::class);

// Replay all user events from last month
$from = new \DateTime('-30 days');
$streamingBus->replayEventsByType(UserCreated::class);

// Analyze event patterns
$stats = $streamingBus->getHistoryStats();
// ['total_events' => 1000, 'event_types' => ['UserCreated' => 500, ...]]
```

## Troubleshooting

### Event Streaming Issues

**Events không được stream:**
- Check `EVENT_STREAMING_ENABLED=true`
- Verify broker connectivity
- Check logs for errors

**High latency:**
- Tune broker configuration
- Consider async publishing
- Monitor network latency

### Read Model Issues

**Denormalization fails:**
- Check table/column names
- Verify join keys exist
- Check data types match

**Materialized view refresh fails:**
- Check view SQL syntax
- Verify table permissions
- Check database driver support

## Performance Tips

1. **Batch Events**: Batch multiple events khi có thể
2. **Async Streaming**: Use async producers để không block
3. **Selective Denormalization**: Chỉ denormalize fields cần thiết
4. **Refresh Strategy**: Refresh materialized views off-peak hours
5. **Index Maintenance**: Monitor và optimize indexes định kỳ

## Kết luận

Event Streaming và Read Model Optimization cung cấp:

- ✅ **Event replay** cho analytics và debugging
- ✅ **Time-travel debugging** với event history
- ✅ **Optimized read models** với denormalization
- ✅ **Materialized views** cho fast queries
- ✅ **Auto-indexing** dựa trên query patterns

Enable các features theo nhu cầu và monitor performance.
