# Development Experience Guide

## Tổng quan

Hệ thống Development Experience đã được triển khai với:

1. **Hot Reload với Advanced Features** - Smart file watching với dependency tracking
2. **Visual Debugging Tools** - Request flow visualization và performance profiling

## 1. Hot Reload với Advanced Features

### Cấu hình

Thêm vào `.env`:
```env
HOT_RELOAD_ENABLED=true
HOT_RELOAD_INTERVAL=500
HOT_RELOAD_DEPENDENCY_TRACKING=true
HOT_RELOAD_INCREMENTAL=true
HOT_RELOAD_FAST_REFRESH=true
```

### Features

- ✅ **Smart file watching** - Efficient file change detection
- ✅ **Dependency tracking** - Track file dependencies automatically
- ✅ **Incremental compilation** - Only compile changed files
- ✅ **Fast refresh** - Reload only affected parts
- ✅ **OPcache invalidation** - Clear opcache for changed PHP files
- ✅ **Blade cache clearing** - Clear compiled Blade templates

### Sử dụng

#### Basic Usage

```php
use Core\Development\HotReload;

$hotReload = app(HotReload::class);

// Start watching (runs in background)
$hotReload->watch();
```

#### Integration với Server

Hot reload tự động được tích hợp vào Swoole server khi chạy trong development mode:

```bash
php cli serve:watch
```

### Dependency Tracking

Hot reload tự động detect dependencies bằng cách:
- Parse `use` statements trong PHP files
- Parse `require`/`include` statements
- Build dependency map và cache
- Track affected files khi có changes

### Incremental Compilation

Chỉ compile files đã thay đổi:
- PHP files: Clear OPcache
- Blade templates: Clear compiled views
- Config files: Clear config cache

### Fast Refresh

Fast refresh chỉ reload affected parts:
- PHP files: Clear OPcache
- Config files: Clear config cache
- View files: Clear view cache

## 2. Visual Debugging Tools

### Cấu hình

Thêm vào `.env`:
```env
VISUAL_DEBUGGING_ENABLED=true
VISUAL_DEBUG_REQUEST_FLOW=true
VISUAL_DEBUG_QUERY_ANALYZER=true
VISUAL_DEBUG_PERFORMANCE=true
VISUAL_DEBUG_MEMORY_LEAK=true
VISUAL_DEBUG_FLAME_GRAPH=true
VISUAL_DEBUG_SLOW_QUERY=100
```

### Features

- ✅ **Request flow visualization** - Track request steps
- ✅ **Database query analyzer** - Analyze query performance
- ✅ **Performance profiler** - Profile request performance
- ✅ **Flame graphs** - Visualize performance data
- ✅ **Memory leak detector** - Detect memory leaks

### Sử dụng

#### Track Request Flow

```php
use Core\Development\VisualDebugger;

$debugger = app(VisualDebugger::class);

// Start tracking
$requestId = uniqid('req_');
$debugger->startRequest($requestId, [
    'method' => $request->getMethod(),
    'uri' => $request->getUri()->getPath(),
]);

// Add steps
$debugger->addStep($requestId, 'middleware', ['name' => 'auth']);
$debugger->addStep($requestId, 'controller', ['name' => 'UserController']);
$debugger->addStep($requestId, 'database', ['query' => 'SELECT * FROM users']);

// End tracking
$flow = $debugger->endRequest($requestId);
```

#### Record Database Queries

```php
$startTime = microtime(true);
$result = DB::table('users')->get();
$duration = (microtime(true) - $startTime) * 1000; // ms

$debugger->recordQuery($requestId, 'SELECT * FROM users', $duration);
```

#### Analyze Queries

```php
// Analyze queries for a request
$analysis = $debugger->analyzeQueries($requestId);

// Or analyze all queries
$analysis = $debugger->analyzeQueries();

// Returns:
// [
//     'total_queries' => 10,
//     'total_duration' => 150.5,
//     'average_duration' => 15.05,
//     'slow_queries' => [...],
//     'duplicate_queries' => [...],
//     'queries_by_table' => [...],
// ]
```

#### Memory Leak Detection

```php
// Take memory snapshots
$debugger->takeMemorySnapshot($requestId, 'start');
// ... do work ...
$debugger->takeMemorySnapshot($requestId, 'middle');
// ... do more work ...
$debugger->takeMemorySnapshot($requestId, 'end');

// Check for leaks
$flow = $debugger->endRequest($requestId);
if ($flow['memory_leak']['detected']) {
    Log::warning("Memory leak detected", $flow['memory_leak']);
}
```

#### Performance Profile

```php
$profile = $debugger->getPerformanceProfile($requestId);

// Returns:
// [
//     'duration' => 150.5,
//     'memory_peak' => 10485760,
//     'memory_usage' => 5242880,
//     'queries_count' => 10,
//     'queries_duration' => 50.2,
//     'steps_count' => 5,
//     'flame_graph' => [...],
// ]
```

#### Flame Graph Data

```php
$flow = $debugger->getRequestFlow($requestId);
$flameGraph = $flow['flame_graph'];

// Flame graph data format:
// [
//     [
//         'name' => 'middleware',
//         'start' => 0,
//         'duration' => 10.5,
//         'memory' => 1048576,
//     ],
//     [
//         'name' => 'controller',
//         'start' => 10.5,
//         'duration' => 50.2,
//         'memory' => 2097152,
//     ],
//     // ...
// ]
```

## Examples

### Example 1: Middleware Integration

```php
use Core\Development\VisualDebugger;

class DebugMiddleware
{
    public function handle($request, $next, VisualDebugger $debugger)
    {
        $requestId = uniqid('req_');
        
        $debugger->startRequest($requestId, [
            'method' => $request->getMethod(),
            'uri' => $request->getUri()->getPath(),
        ]);
        
        $debugger->addStep($requestId, 'middleware.start');
        
        $response = $next($request);
        
        $debugger->addStep($requestId, 'middleware.end');
        $flow = $debugger->endRequest($requestId);
        
        // Log performance
        Log::info("Request completed", [
            'duration' => $flow['duration'],
            'memory' => $flow['memory_peak'],
        ]);
        
        return $response;
    }
}
```

### Example 2: Database Query Tracking

```php
use Core\Development\VisualDebugger;

class QueryTrackingMiddleware
{
    public function handle($request, $next, VisualDebugger $debugger)
    {
        $requestId = uniqid('req_');
        
        // Wrap database queries
        DB::listen(function ($query) use ($debugger, $requestId) {
            $debugger->recordQuery(
                $requestId,
                $query->sql,
                $query->time,
                $query->bindings
            );
        });
        
        return $next($request);
    }
}
```

### Example 3: Memory Leak Detection

```php
use Core\Development\VisualDebugger;

class MemoryLeakMiddleware
{
    public function handle($request, $next, VisualDebugger $debugger)
    {
        $requestId = uniqid('req_');
        
        $debugger->takeMemorySnapshot($requestId, 'start');
        
        $response = $next($request);
        
        $debugger->takeMemorySnapshot($requestId, 'end');
        $flow = $debugger->endRequest($requestId);
        
        if ($flow['memory_leak']['detected'] ?? false) {
            Log::warning("Potential memory leak", [
                'growth' => $flow['memory_leak']['growth'],
                'growth_percent' => $flow['memory_leak']['growth_percent'],
            ]);
        }
        
        return $response;
    }
}
```

## Best Practices

### Hot Reload

1. **Dependency Tracking**: Enable để track dependencies automatically
2. **Incremental Compilation**: Chỉ compile changed files
3. **Fast Refresh**: Use fast refresh để giảm reload time
4. **Ignore Patterns**: Configure ignore patterns để exclude unnecessary files

### Visual Debugging

1. **Request Tracking**: Track requests trong development mode
2. **Query Analysis**: Analyze queries để optimize performance
3. **Memory Monitoring**: Monitor memory usage để detect leaks
4. **Performance Profiling**: Profile requests để identify bottlenecks

## Troubleshooting

### Hot Reload Issues

**Files not reloading:**
- Check `HOT_RELOAD_ENABLED=true`
- Verify directories are watched
- Check ignore patterns
- Verify file permissions

**Slow reload:**
- Increase interval
- Optimize ignore patterns
- Reduce watched directories

### Visual Debugging Issues

**No data collected:**
- Check `VISUAL_DEBUGGING_ENABLED=true`
- Verify middleware is registered
- Check request tracking is started

**High memory usage:**
- Limit tracked requests
- Clear old data regularly
- Disable in production

## Performance Tips

1. **Hot Reload**: Use incremental compilation
2. **Visual Debugging**: Only enable in development
3. **Memory Snapshots**: Limit snapshot frequency
4. **Query Analysis**: Analyze queries off-peak
5. **Flame Graphs**: Generate flame graphs on-demand

## Kết luận

Development Experience cung cấp:

- ✅ **Hot reload** với dependency tracking
- ✅ **Incremental compilation** cho faster reloads
- ✅ **Visual debugging** với request flow visualization
- ✅ **Performance profiling** với flame graphs
- ✅ **Memory leak detection** để prevent issues
- ✅ **Query analyzer** để optimize database queries

Enable các features trong development mode để improve development experience.
