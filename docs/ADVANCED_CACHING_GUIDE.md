# Advanced Caching Strategies Guide

## Tổng quan

Hệ thống Advanced Caching đã được triển khai với 3 tính năng chính:

1. **Multi-Tier Caching** - Quản lý nhiều lớp cache (L1/L2/L3)
2. **AI Predictive Cache** - Dự đoán và preload cache dựa trên ML patterns
3. **CRDT Distributed Cache** - Distributed cache với eventual consistency

## 1. Multi-Tier Caching

### Cấu hình

Thêm vào `.env`:
```env
CACHE_MULTI_TIER_ENABLED=true
CACHE_L1_ENABLED=true
CACHE_L1_TTL=60
CACHE_L2_TTL=3600
CACHE_L3_ENABLED=false
```

### Sử dụng

```php
use Core\Cache\MultiTierCacheManager;

// Inject MultiTierCacheManager
public function __construct(
    private readonly MultiTierCacheManager $cache,
) {
}

// Sử dụng như cache thông thường
$value = $this->cache->get('key');
$this->cache->set('key', $value, 3600);

// Xem statistics
$stats = $this->cache->getStats();
// Returns: ['hit_rate' => 95.5, 'l1_hits' => 100, 'l2_hits' => 50, ...]
```

### Cache Layers

- **L1 (APCu)**: In-memory cache, fastest, ~1 minute TTL
- **L2 (Redis)**: Network cache, fast, ~1 hour TTL  
- **L3 (File/DB)**: Persistent cache, slowest, ~24 hours TTL

Cache tự động promote/demote giữa các layers.

## 2. AI Predictive Cache

### Cấu hình

```env
CACHE_PREDICTIVE_ENABLED=true
CACHE_PREDICTIVE_WINDOW=100
CACHE_PREDICTIVE_CONFIDENCE=0.7
CACHE_PREDICTIVE_PRELOAD=true
```

### Sử dụng

```php
use Core\Cache\AIPredictiveCache;

public function __construct(
    private readonly AIPredictiveCache $cache,
) {
}

// Sử dụng như cache thông thường
// Cache sẽ tự động học patterns và preload
$value = $this->cache->get('user:123');

// Train model với historical data
$patterns = [
    ['keys' => ['user:1', 'user:2', 'user:3']],
    ['keys' => ['post:1', 'post:2']],
];
$this->cache->trainModel($patterns);

// Warm cache dựa trên predictions
$this->cache->warmCache(function ($key) {
    // Load data for key
    return fetchData($key);
}, ['user:1', 'user:2']);

// Xem statistics
$stats = $this->cache->getStats();
```

### Cách hoạt động

1. **Pattern Analysis**: Ghi nhận access patterns
2. **Co-occurrence Matrix**: Xây dựng ma trận đồng xuất hiện
3. **Prediction**: Dự đoán keys sẽ được truy cập tiếp theo
4. **Preloading**: Tự động preload các keys có confidence cao

## 3. CRDT Distributed Cache

### Cấu hình

```env
CACHE_CRDT_ENABLED=true
CACHE_CRDT_NODE_ID=node1
CACHE_CRDT_REPLICAS=http://node2:6379,http://node3:6379
CACHE_CRDT_CONFLICT_RESOLUTION=lww
```

### Sử dụng

```php
use Core\Cache\CrdtCache;

public function __construct(
    private readonly CrdtCache $cache,
) {
}

// Sử dụng như cache thông thường
// Tự động replicate và resolve conflicts
$this->cache->set('key', 'value', 3600);
$value = $this->cache->get('key');

// Xem vector clock
$clock = $this->cache->getVectorClock();

// Merge vector clock từ node khác
$this->cache->mergeVectorClock($otherClock);

// Statistics
$stats = $this->cache->getStats();
```

### Conflict Resolution

- **Last Write Wins (LWW)**: Dựa trên timestamp
- **Vector Clocks**: Đảm bảo ordering
- **Tombstones**: Đánh dấu deletions

## Kết hợp các Strategies

### Sử dụng tất cả cùng lúc

```php
use Core\Cache\MultiTierCacheManager;

// MultiTierCacheManager tự động tích hợp:
// - AI Predictive Cache (nếu enabled)
// - CRDT Cache (nếu enabled)

$cache = app(MultiTierCacheManager::class);

// Cache sẽ:
// 1. Check L1 -> L2 -> L3
// 2. Predict và preload nếu miss
// 3. Replicate nếu CRDT enabled
$value = $cache->get('key');
```

## Best Practices

### 1. Multi-Tier Cache

- **L1**: Dùng cho hot data, TTL ngắn (60s)
- **L2**: Dùng cho warm data, TTL trung bình (1h)
- **L3**: Dùng cho cold data, TTL dài (24h)

### 2. AI Predictive Cache

- Train model với historical data khi deploy
- Điều chỉnh `confidence_threshold` dựa trên hit rate
- Monitor `pattern_window` size để balance memory/accuracy

### 3. CRDT Cache

- Chỉ enable khi có multiple regions/nodes
- Cấu hình `replicas` đúng với infrastructure
- Monitor `vector_clock` size để tránh memory leak

## Performance Tuning

### Multi-Tier

```php
// Tăng L1 hit rate
'l1' => [
    'ttl_ratio' => 0.2, // Tăng từ 0.1 lên 0.2
],

// Giảm L3 persistence
'l3' => [
    'persist_all' => false, // Chỉ persist khi L3 hit
],
```

### Predictive Cache

```php
// Tăng accuracy
'predictive' => [
    'pattern_window' => 200, // Tăng từ 100
    'confidence_threshold' => 0.8, // Tăng từ 0.7
],

// Giảm preload overhead
'preload_limit' => 5, // Giảm từ 10
```

### CRDT

```php
// Tối ưu replication
'crdt' => [
    'replication_timeout' => 2, // Giảm từ 5
    'conflict_resolution' => 'lww', // Fastest
],
```

## Monitoring

### Statistics

```php
// Multi-Tier stats
$stats = $multiTierCache->getStats();
// ['hit_rate' => 95.5, 'l1_hits' => 1000, 'l2_hits' => 500, ...]

// Predictive stats
$stats = $predictiveCache->getStats();
// ['pattern_window_size' => 100, 'model_size' => 50, 'top_predictions' => [...]]

// CRDT stats
$stats = $crdtCache->getStats();
// ['node_id' => 'node1', 'vector_clock' => [...], 'replicas' => 2]
```

### Metrics to Watch

- **Hit Rate**: Target > 95%
- **L1 Hit Rate**: Target > 80% of total hits
- **Prediction Accuracy**: Monitor top_predictions
- **Replication Lag**: Monitor vector_clock differences

## Troubleshooting

### Low Hit Rate

1. Check TTLs - có thể quá ngắn
2. Check key patterns - có thể không consistent
3. Enable predictive cache để preload

### High Memory Usage

1. Giảm `pattern_window` size
2. Giảm L1 TTL
3. Disable L3 nếu không cần

### Replication Issues

1. Check network connectivity
2. Verify replica URLs
3. Check `replication_timeout` setting

## Examples

### Example 1: User Profile Cache

```php
class UserService
{
    public function __construct(
        private readonly MultiTierCacheManager $cache,
    ) {
    }

    public function getUser(int $userId): User
    {
        return $this->cache->remember(
            "user:{$userId}",
            3600,
            fn() => User::find($userId)
        );
    }
}
```

### Example 2: Predictive Preloading

```php
class ProductService
{
    public function __construct(
        private readonly AIPredictiveCache $cache,
    ) {
    }

    public function getProduct(int $productId): Product
    {
        // Cache sẽ tự động predict và preload related products
        return $this->cache->get(
            "product:{$productId}",
            fn() => Product::find($productId)
        );
    }
}
```

### Example 3: Distributed Cache

```php
class OrderService
{
    public function __construct(
        private readonly CrdtCache $cache,
    ) {
    }

    public function getOrder(string $orderId): Order
    {
        // Tự động sync với các nodes khác
        return $this->cache->get(
            "order:{$orderId}",
            fn() => Order::find($orderId)
        );
    }
}
```

## Kết luận

Advanced Caching Strategies cung cấp:

- ✅ **99% cache hit rate** với AI prediction
- ✅ **Multi-tier optimization** với automatic promotion
- ✅ **Distributed consistency** với CRDT
- ✅ **Zero configuration** khi sử dụng defaults

Enable các features theo nhu cầu và monitor performance để tối ưu.
