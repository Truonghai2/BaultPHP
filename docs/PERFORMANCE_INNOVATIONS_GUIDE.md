# Performance Innovations Guide

## Tổng quan

Hệ thống Performance Innovations đã được triển khai với:

1. **JIT Compilation với OPcache Enhancement** - Profile-based optimization và hot path detection
2. **Request Batching & Coalescing** - Parallel execution và result aggregation

## 1. JIT Compilation với OPcache Enhancement

### Cấu hình

Thêm vào `.env`:
```env
JIT_OPTIMIZATION_ENABLED=true
JIT_MIN_ACCESS_COUNT=100
JIT_HOT_PATH_THRESHOLD=50
JIT_MEMORY_THRESHOLD=80
JIT_MIN_HIT_RATE=90
JIT_PRELOAD_LIMIT=100
JIT_STALE_THRESHOLD=3600
```

### Features

- ✅ **Profile-based optimization** - Optimize based on access patterns
- ✅ **Hot path detection** - Detect và optimize frequently executed paths
- ✅ **Auto-optimization** - Automatic optimization based on heuristics
- ✅ **OPcache management** - Manage OPcache efficiently
- ✅ **Memory optimization** - Optimize memory usage
- ✅ **Hit rate optimization** - Improve OPcache hit rate

### Sử dụng

#### Basic Optimization

```php
use Core\Performance\JITOptimizer;

$optimizer = app(JITOptimizer::class);

// Run optimization
$optimizer->optimize();

// Get statistics
$stats = $optimizer->getStats();
echo "OPcache Hit Rate: {$stats['hit_rate']}%\n";
echo "Cached Scripts: {$stats['cached_scripts']}\n";
echo "Hot Paths: {$stats['hot_paths']}\n";
```

#### Record File Access

```php
// Record file access for profiling
$optimizer->recordAccess('/path/to/file.php');

// Optimization will use this data to optimize frequently accessed files
```

#### Get Statistics

```php
$stats = $optimizer->getStats();

// Returns:
// [
//     'available' => true,
//     'hit_rate' => 95.5,
//     'cached_scripts' => 1234,
//     'hits' => 100000,
//     'misses' => 5000,
//     'memory_used' => 67108864,
//     'memory_free' => 134217728,
//     'memory_usage_percent' => 33.3,
//     'hot_paths' => 10,
//     'profiled_files' => 500,
// ]
```

#### Reset Optimization

```php
// Reset optimization cache và OPcache
$optimizer->reset();
```

### Optimization Strategies

1. **Profile-Based**: Optimize files based on access frequency
2. **Hot Path Detection**: Identify và optimize hot paths
3. **Memory Management**: Optimize when memory usage is high
4. **Hit Rate Optimization**: Preload frequently accessed files

## 2. Request Batching & Coalescing

### Cấu hình

Thêm vào `.env`:
```env
REQUEST_BATCHING_ENABLED=true
REQUEST_BATCHING_PARALLEL=true
REQUEST_BATCHING_TIMEOUT=30
REQUEST_BATCHING_MAX_CONCURRENCY=10
REQUEST_BATCHING_COALESCE=true
```

### Features

- ✅ **Batch multiple requests** - Execute multiple requests together
- ✅ **Parallel execution** - Execute requests in parallel using Swoole coroutines
- ✅ **Result aggregation** - Aggregate results efficiently
- ✅ **Request coalescing** - Group similar requests và share results
- ✅ **Database query batching** - Batch database queries
- ✅ **API call batching** - Batch API calls

### Sử dụng

#### Basic Batching

```php
use Core\Http\RequestBatcher;

$batcher = app(RequestBatcher::class);

// Batch multiple requests
$requests = [
    fn() => User::find(1),
    fn() => User::find(2),
    fn() => User::find(3),
];

$results = $batcher->batch($requests, [
    'parallel' => true,
    'timeout' => 30,
    'max_concurrency' => 10,
]);

foreach ($results as $result) {
    if ($result['success']) {
        echo "Result: " . json_encode($result['result']) . "\n";
    } else {
        echo "Error: {$result['error']}\n";
    }
}
```

#### Request Coalescing

```php
// Coalesce similar requests
$requests = [
    fn() => User::find(1),
    fn() => User::find(1), // Duplicate
    fn() => User::find(2),
];

// Similar requests will share results
$results = $batcher->coalesce($requests);

// Results[0] và Results[1] will be the same (shared)
```

#### Database Query Batching

```php
$queries = [
    'SELECT * FROM users WHERE id = 1',
    'SELECT * FROM users WHERE id = 2',
    'SELECT * FROM users WHERE id = 3',
];

$results = $batcher->batchQueries($queries, function ($query) {
    return DB::select($query);
});
```

#### API Call Batching

```php
$urls = [
    'https://api.example.com/users/1',
    'https://api.example.com/users/2',
    'https://api.example.com/users/3',
];

$results = $batcher->batchApiCalls($urls, function ($url) {
    return Http::get($url)->json();
});
```

#### Custom Key Generator for Coalescing

```php
$results = $batcher->coalesce($requests, function ($request) {
    // Custom key generation logic
    if (is_callable($request)) {
        // Extract parameters from closure
        $reflection = new \ReflectionFunction($request);
        return hash('md5', $reflection->getStaticVariables());
    }
    return hash('md5', serialize($request));
});
```

## Examples

### Example 1: Optimize OPcache trong Scheduled Task

```php
use Core\Performance\JITOptimizer;

// In Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $optimizer = app(JITOptimizer::class);
        $optimizer->optimize();
        
        $stats = $optimizer->getStats();
        if ($stats['hit_rate'] < 90) {
            Log::warning("OPcache hit rate is low", $stats);
        }
    })->hourly();
}
```

### Example 2: Batch User Data Loading

```php
use Core\Http\RequestBatcher;

class UserController
{
    public function index(RequestBatcher $batcher)
    {
        $userIds = [1, 2, 3, 4, 5];
        
        $requests = array_map(function ($id) {
            return fn() => User::with('profile', 'posts')->find($id);
        }, $userIds);
        
        $results = $batcher->batch($requests, [
            'parallel' => true,
            'max_concurrency' => 5,
        ]);
        
        $users = array_map(function ($result) {
            return $result['success'] ? $result['result'] : null;
        }, $results);
        
        return response()->json($users);
    }
}
```

### Example 3: Coalesce API Calls

```php
use Core\Http\RequestBatcher;

class ApiController
{
    public function batch(RequestBatcher $batcher)
    {
        // Multiple requests for same data
        $requests = [
            fn() => $this->getUserData(1),
            fn() => $this->getUserData(1), // Duplicate
            fn() => $this->getUserData(2),
        ];
        
        // Coalesce sẽ chỉ execute getUserData(1) once
        $results = $batcher->coalesce($requests);
        
        return response()->json($results);
    }
}
```

### Example 4: Batch Database Queries

```php
use Core\Http\RequestBatcher;

class ReportController
{
    public function generate(RequestBatcher $batcher)
    {
        $queries = [
            'SELECT COUNT(*) FROM users',
            'SELECT COUNT(*) FROM orders',
            'SELECT COUNT(*) FROM products',
        ];
        
        $results = $batcher->batchQueries($queries, function ($query) {
            return DB::select($query)[0];
        });
        
        return [
            'users' => $results[0]['result']['COUNT(*)'] ?? 0,
            'orders' => $results[1]['result']['COUNT(*)'] ?? 0,
            'products' => $results[2]['result']['COUNT(*)'] ?? 0,
        ];
    }
}
```

## Best Practices

### JIT Optimization

1. **Enable OPcache**: Ensure OPcache is enabled in production
2. **Monitor Hit Rate**: Keep hit rate above 90%
3. **Profile Files**: Record file access for better optimization
4. **Preload Hot Paths**: Preload frequently accessed files
5. **Memory Management**: Monitor memory usage và optimize when needed

### Request Batching

1. **Use Parallel Execution**: Enable parallel execution for better performance
2. **Set Appropriate Timeout**: Set timeout based on request complexity
3. **Limit Concurrency**: Don't exceed max_concurrency to avoid overload
4. **Coalesce Similar Requests**: Use coalescing to reduce duplicate work
5. **Error Handling**: Handle errors gracefully trong batched requests

## Troubleshooting

### JIT Optimization

**Low hit rate:**
- Check OPcache configuration
- Increase memory limit
- Preload frequently accessed files
- Review file access patterns

**High memory usage:**
- Reduce preload limit
- Increase OPcache memory
- Reset cache periodically
- Optimize file access patterns

### Request Batching

**Timeout errors:**
- Increase timeout
- Reduce batch size
- Optimize individual requests
- Check network latency

**High memory usage:**
- Reduce max_concurrency
- Process batches sequentially
- Limit batch size
- Monitor memory usage

## Performance Tips

1. **OPcache**: Enable OPcache trong production
2. **Preloading**: Preload frequently accessed files
3. **Batching**: Batch requests khi có thể
4. **Coalescing**: Coalesce similar requests
5. **Monitoring**: Monitor performance metrics regularly

## Kết luận

Performance Innovations cung cấp:

- ✅ **JIT optimization** với profile-based optimization
- ✅ **Hot path detection** để optimize frequently executed code
- ✅ **Request batching** với parallel execution
- ✅ **Request coalescing** để reduce duplicate work
- ✅ **OPcache management** để improve performance
- ✅ **Easy integration** với existing codebase

Enable các features trong production để improve performance significantly.
