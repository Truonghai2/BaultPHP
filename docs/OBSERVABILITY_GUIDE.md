# Observability & Monitoring Guide

## Tổng quan

Hệ thống Observability & Monitoring đã được triển khai với:

1. **OpenTelemetry Integration** - Distributed tracing và performance monitoring
2. **AI-Powered Anomaly Detection** - ML-based anomaly detection và auto-alerting

## 1. OpenTelemetry Integration

### Cấu hình

Thêm vào `.env`:
```env
OTEL_ENABLED=true
OTEL_SERVICE_NAME=baultphp
OTEL_SERVICE_VERSION=1.0.0
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
OTEL_SAMPLING_RATE=1.0
```

### Features

- ✅ **Distributed tracing** - Trace requests across services
- ✅ **Performance monitoring** - Track operation durations
- ✅ **Error tracking** - Automatic exception recording
- ✅ **Metrics collection** - Collect custom metrics
- ✅ **Automatic instrumentation** - HTTP, DB, Cache, Queue, API calls

### Sử dụng

#### Basic Tracing

```php
use Core\Observability\OpenTelemetryTracer;

$tracer = app(OpenTelemetryTracer::class);

// Trace an operation
$result = $tracer->trace('user.registration', function () {
    // Your operation code
    return User::create($data);
}, [
    'user.email' => $email,
    'user.source' => 'web',
]);
```

#### HTTP Request Tracing

```php
$tracer->traceHttpRequest('POST', '/api/users', function () {
    return $this->createUser($data);
});
```

#### Database Query Tracing

```php
$tracer->traceDbQuery(
    'SELECT * FROM users WHERE id = ?',
    [$userId],
    function () use ($userId) {
        return User::find($userId);
    }
);
```

#### Cache Operation Tracing

```php
$tracer->traceCacheOperation('get', 'user:123', function () {
    return Cache::get('user:123');
});
```

#### External API Call Tracing

```php
$tracer->traceApiCall('payment-gateway', 'https://api.payment.com/charge', function () {
    return Http::post('https://api.payment.com/charge', $data);
});
```

#### Manual Span Management

```php
// Start span
$span = $tracer->startSpan('custom-operation', [
    'operation.type' => 'data-processing',
]);

try {
    // Your code
    $result = processData($data);
    
    // Add attributes
    $tracer->setAttribute('items.processed', count($result));
    
    // Add events
    $tracer->addEvent('processing.complete', [
        'duration_ms' => 150,
    ]);
    
    return $result;
} catch (\Throwable $e) {
    // Record exception
    $tracer->recordException($e);
    throw $e;
} finally {
    // End span
    $tracer->endSpan($span);
}
```

### Span Attributes

Common span attributes:
- `http.method` - HTTP method
- `http.url` - Request URL
- `http.status_code` - Response status code
- `db.system` - Database system (mysql, postgresql, etc.)
- `db.statement` - SQL query (sanitized)
- `db.operation` - Operation type (SELECT, INSERT, etc.)
- `cache.operation` - Cache operation (get, set, delete)
- `cache.key` - Cache key
- `service.name` - Service name
- `error` - Error flag
- `error.message` - Error message

### Integration với Middleware

```php
use Core\Observability\OpenTelemetryTracer;

class TracingMiddleware
{
    public function handle($request, $next)
    {
        $tracer = app(OpenTelemetryTracer::class);
        
        return $tracer->traceHttpRequest(
            $request->method(),
            $request->fullUrl(),
            fn() => $next($request)
        );
    }
}
```

## 2. AI-Powered Anomaly Detection

### Cấu hình

Thêm vào `.env`:
```env
ANOMALY_DETECTION_ENABLED=true
ANOMALY_Z_SCORE_CRITICAL=4.0
ANOMALY_Z_SCORE_HIGH=3.0
ANOMALY_Z_SCORE_MEDIUM=2.0
ANOMALY_TREND_THRESHOLD=0.1
ANOMALY_PATTERN_THRESHOLD=0.5
```

### Features

- ✅ **ML-based detection** - Statistical anomaly detection
- ✅ **Auto-alerting** - Automatic alerts on anomalies
- ✅ **Performance degradation detection** - Detect performance issues
- ✅ **Pattern recognition** - Detect unusual patterns
- ✅ **Trend analysis** - Detect degrading trends
- ✅ **Z-score detection** - Statistical outlier detection

### Sử dụng

#### Record Metrics

```php
use Core\Observability\AnomalyDetector;

$detector = app(AnomalyDetector::class);

// Record response time
$detector->recordMetric('response_time', 150.5, [
    'endpoint' => '/api/users',
    'method' => 'GET',
]);

// Record error rate
$detector->recordMetric('error_rate', 0.05, [
    'service' => 'user-service',
]);

// Record CPU usage
$detector->recordMetric('cpu_usage', 85.0, [
    'server' => 'web-1',
]);
```

#### Detect Anomalies

```php
// Detect all anomalies
$anomalies = $detector->detectAnomalies();

foreach ($anomalies as $anomaly) {
    echo "Anomaly detected: {$anomaly['metric']}\n";
    echo "Value: {$anomaly['value']}\n";
    echo "Severity: {$anomaly['severity']}\n";
    echo "Z-score: {$anomaly['z_score']}\n";
}
```

#### Update Baselines

```php
// Update baseline for a metric
$detector->updateBaseline('response_time', [
    'mean' => 120,
    'stddev' => 25,
    'p95' => 180,
    'p99' => 250,
]);
```

#### Get Recent Alerts

```php
// Get recent alerts
$alerts = $detector->getRecentAlerts(10);

foreach ($alerts as $alert) {
    echo "Alert: {$alert['metric']} = {$alert['value']}\n";
    echo "Severity: {$alert['severity']}\n";
    echo "Time: " . date('Y-m-d H:i:s', $alert['timestamp']) . "\n";
}
```

#### Custom Alert Callback

```php
// In config/observability.php
'anomaly_detection' => [
    'alert_callback' => function ($alert) {
        // Send to Slack, email, etc.
        if ($alert['severity'] === 'critical') {
            Slack::send('#alerts', "Critical anomaly: {$alert['metric']}");
        }
    },
],
```

### Anomaly Types

1. **Z-score anomalies** - Values outside 2-3 standard deviations
2. **Trend anomalies** - Degrading performance trends
3. **Pattern anomalies** - Unusual patterns (spikes, drops)

### Severity Levels

- **critical** - Z-score >= 4.0
- **high** - Z-score >= 3.0
- **medium** - Z-score >= 2.0
- **low** - Z-score < 2.0

## Examples

### Example 1: HTTP Request Tracing

```php
use Core\Observability\OpenTelemetryTracer;

class UserController
{
    public function store(Request $request, OpenTelemetryTracer $tracer)
    {
        return $tracer->traceHttpRequest('POST', $request->fullUrl(), function () use ($request) {
            $user = User::create($request->validated());
            
            $tracer->addEvent('user.created', [
                'user_id' => $user->id,
            ]);
            
            return response()->json($user);
        });
    }
}
```

### Example 2: Database Query Tracing

```php
use Core\Observability\OpenTelemetryTracer;

class UserRepository
{
    public function findById($id, OpenTelemetryTracer $tracer)
    {
        return $tracer->traceDbQuery(
            'SELECT * FROM users WHERE id = ?',
            [$id],
            function () use ($id) {
                return User::find($id);
            }
        );
    }
}
```

### Example 3: Anomaly Detection trong Middleware

```php
use Core\Observability\AnomalyDetector;

class PerformanceMonitoringMiddleware
{
    public function handle($request, $next, AnomalyDetector $detector)
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $duration = (microtime(true) - $startTime) * 1000; // ms
        
        // Record response time
        $detector->recordMetric('response_time', $duration, [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
        ]);
        
        return $response;
    }
}
```

### Example 4: Scheduled Anomaly Detection

```php
// In Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $detector = app(AnomalyDetector::class);
        $anomalies = $detector->detectAnomalies();
        
        foreach ($anomalies as $anomaly) {
            if ($anomaly['severity'] === 'critical') {
                // Send alert
                Notification::send($admins, new AnomalyAlert($anomaly));
            }
        }
    })->everyMinute();
}
```

## Best Practices

### OpenTelemetry

1. **Span Naming**: Use consistent naming convention (service.operation)
2. **Attributes**: Add relevant attributes for filtering
3. **Sampling**: Adjust sampling rate based on volume
4. **Error Handling**: Always record exceptions
5. **Span Limits**: Keep spans focused and not too broad

### Anomaly Detection

1. **Baseline Updates**: Update baselines regularly
2. **Metric Collection**: Collect metrics consistently
3. **Alert Thresholds**: Tune thresholds to reduce false positives
4. **Context**: Include context tags for better analysis
5. **Cleanup**: Cleanup old metrics regularly

## Troubleshooting

### OpenTelemetry Issues

**Spans not appearing:**
- Check `OTEL_ENABLED=true`
- Verify exporter endpoint is accessible
- Check sampling rate
- Verify OpenTelemetry SDK is installed

**High overhead:**
- Reduce sampling rate
- Limit span attributes
- Use async exporters

### Anomaly Detection Issues

**Too many false positives:**
- Update baselines
- Adjust Z-score thresholds
- Increase sample size

**Missing anomalies:**
- Check baseline accuracy
- Verify metric collection
- Adjust detection thresholds

## Performance Tips

1. **Sampling**: Use sampling for high-volume operations
2. **Async Export**: Use async exporters for traces
3. **Batch Metrics**: Batch metric recording
4. **Baseline Updates**: Update baselines off-peak
5. **Cleanup**: Regular cleanup of old data

## Kết luận

Observability & Monitoring cung cấp:

- ✅ **Distributed tracing** với OpenTelemetry
- ✅ **Performance monitoring** cho tất cả operations
- ✅ **Anomaly detection** với ML-based algorithms
- ✅ **Auto-alerting** cho critical issues
- ✅ **Easy integration** với existing codebase

Enable các features theo nhu cầu và monitor performance.
