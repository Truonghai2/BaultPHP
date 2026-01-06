# Debug Bar Realtime - Hướng Dẫn Chi Tiết

## 🎯 Tổng Quan

BaultFrame có **Debug Bar với realtime updates** qua WebSocket. Debug bar sẽ tự động cập nhật metrics khi có operations xảy ra (queries, events, cache, etc) **KHÔNG CẦN REFRESH**.

## ⚠️ Vấn Đề: Debug Bar Không Cập Nhật

### Triệu Chứng

```
Queries: 0    Events: 0    Cache: -    Session: 0    Cookies: 0
```

Tất cả metrics = 0 và không thay đổi khi bạn thực hiện actions.

### Nguyên Nhân

#### 1. **APP_DEBUG=false hoặc chưa set** (CRITICAL)

```php
// config/debug.php
'enabled' => env('APP_DEBUG', false),

// DatabaseServiceProvider.php
if ((bool) config('debug.enabled', false)) {
    // Wrap PDO với RealtimeTraceablePdo để track queries
}
```

**Hậu quả:**

- Không có debug proxies được enable
- Queries, events, cache operations không được track
- Debug bar chỉ hiển thị thông tin request cơ bản

**Giải pháp:**

```bash
# Thêm vào .env
APP_DEBUG=true
```

#### 2. **Không Có Operations Thực Sự Xảy Ra**

Debug bar chỉ hiển thị data KHI có operations:

- **Queries: 0** → Chưa có database queries
- **Events: 0** → Chưa có events được dispatch
- **Cache: -** → Chưa có cache operations

**Giải pháp:** Thực hiện actions để trigger operations:

- Visit pages có database queries
- Trigger events
- Access cached data

#### 3. **WebSocket Không Connect**

Realtime updates cần WebSocket connection.

**Check WebSocket:**

1. Mở Browser DevTools → Console
2. Tìm messages: `WebSocket connected` hoặc `WebSocket connection failed`
3. Check Network tab → WS filter

**Nguyên nhân WebSocket fail:**

- WebSocket server không chạy
- Port bị block
- Browser không support WebSocket

#### 4. **Redis Không Available** (Optional)

Redis dùng để lưu debug data (không bắt buộc cho realtime):

```bash
# Check Redis
docker-compose ps redis
docker-compose logs redis

# Start Redis
docker-compose up -d redis
```

## 🔧 Cách Hoạt Động

### Architecture

```
Request → CollectDebugDataMiddleware
              ↓
         DebugManager.enable()
         DebugBroadcaster.enable()
              ↓
    ┌─────────┴─────────┐
    │                   │
    ↓                   ↓
Query Execute      Event Dispatch
    ↓                   ↓
RealtimeTraceablePdo  TraceableEventDispatcher
    ↓                   ↓
DebugBroadcaster.broadcastQuery()
    ↓
WebSocket.sendToUser(requestId, data)
    ↓
Browser Debug Bar (Realtime Update!)
```

### Flow Chi Tiết

#### 1️⃣ **Request Start**

```php
// CollectDebugDataMiddleware
$this->debugManager->enable();
$this->broadcaster->enable($requestId);
```

#### 2️⃣ **Query Execution**

```php
// DatabaseServiceProvider wraps PDO
$traceablePdo = new RealtimeTraceablePdo($pdo);
$traceablePdo->setBroadcaster($broadcaster);

// Khi query execute
$stmt = $pdo->prepare("SELECT * FROM users");
$stmt->execute();

// ↓ RealtimeTraceablePdo intercept

$broadcaster->broadcastQuery([
    'sql' => 'SELECT * FROM users',
    'duration_ms' => 12.5,
    'row_count' => 100,
]);

// ↓ WebSocket broadcast

wsManager->sendToUser($requestId, [
    'type' => 'debug_realtime',
    'payload' => [
        'type' => 'query',
        'data' => [...],
    ],
]);
```

#### 3️⃣ **Browser Receives Update**

```javascript
// resources/views/debug/bar.blade.php
BaultDebugBar.initWebSocket() {
    this.ws.onmessage = (event) => {
        const message = JSON.parse(event.data);

        if (message.type === 'debug_realtime') {
            this.handleRealtimeUpdate(message.payload);
        }
    };
}

handleRealtimeUpdate(payload) {
    switch(payload.type) {
        case 'query':
            this.handleQueryUpdate(payload.data);
            // ↓ Update UI
            // Queries: 0 → Queries: 1
            break;
    }
}
```

## 🚀 Setup & Testing

### Bước 1: Check Configuration

```bash
php fix-debug-bar.php
```

Output mong đợi:

```
✅ APP_DEBUG đã được set = true
✅ Debug bar configuration OK!
```

### Bước 2: Restart Server

```bash
docker-compose restart
```

### Bước 3: Test Realtime Updates

#### Test 1: Database Queries

```php
// routes/web.php
Route::get('/debug-test/query', function () {
    $users = \Modules\User\Infrastructure\Models\User::all();
    return response()->json([
        'count' => $users->count(),
        'message' => 'Check debug bar - Queries should increment!',
    ]);
});
```

**Expected:**

- Visit: `http://localhost:8000/debug-test/query`
- Debug bar: `Queries: 0` → `Queries: 1` (realtime!)

#### Test 2: Events

```php
Route::get('/debug-test/event', function () {
    event(new \Core\Events\ModuleChanged('test'));
    return 'Event dispatched! Check debug bar';
});
```

**Expected:**

- Visit: `http://localhost:8000/debug-test/event`
- Debug bar: `Events: 0` → `Events: 1`

#### Test 3: Cache

```php
Route::get('/debug-test/cache', function () {
    cache()->put('test_key', 'test_value', 60);
    $value = cache()->get('test_key');
    return "Cache set and retrieved: {$value}";
});
```

**Expected:**

- Visit: `http://localhost:8000/debug-test/cache`
- Debug bar: `Cache: -` → `Cache: hit 1, miss 0`

### Bước 4: Check WebSocket Connection

**Browser Console:**

```javascript
// Mở DevTools → Console
// Bạn sẽ thấy:
WebSocket connected
BaultDebugBar: Initializing WebSocket for request ID: 68rdf2854...
```

**Network Tab:**

```
WS    ws://localhost:8000/ws    101 Switching Protocols
```

## 🐛 Troubleshooting

### ❓ Debug Bar Vẫn Không Cập Nhật

#### Kiểm Tra 1: APP_DEBUG

```bash
php -r "require 'bootstrap/app.php'; echo config('debug.enabled') ? 'true' : 'false';"
```

Phải output: `true`

#### Kiểm Tra 2: Debug Proxies

```bash
# Check logs khi server start
tail -f storage/logs/app.log | grep -i debug

# Nên thấy:
# "RealtimeTraceablePdo enabled"
# "DebugBroadcaster registered"
```

#### Kiểm Tra 3: WebSocket

```javascript
// Browser Console
console.log(BaultDebugBar.ws.readyState);
// 0 = CONNECTING
// 1 = OPEN ✅
// 2 = CLOSING
// 3 = CLOSED
```

#### Kiểm Tra 4: Request ID

```bash
# Check response headers
curl -I http://localhost:8000/

# Nên có header:
# X-Debug-ID: 68rdf2854...
```

### ❓ WebSocket Không Connect

#### Nguyên nhân 1: WebSocket Server Không Start

```bash
# Check WebSocket server process
docker-compose exec php ps aux | grep swoole

# Restart server
docker-compose restart php
```

#### Nguyên nhân 2: Port Conflict

```bash
# Check port 8000
netstat -tlnp | grep 8000

# Hoặc trong Docker
docker-compose ps
```

#### Nguyên nhân 3: Browser Settings

- Check browser không block WebSocket
- Disable browser extensions (ad blockers, etc)
- Try incognito mode

### ❓ Chỉ Thấy Request Info, Không Có Queries/Events

**Nguyên nhân:** Page bạn visit không có operations.

**Giải pháp:**

1. Visit page có database queries (ví dụ: dashboard, user list)
2. Login/logout (có session operations)
3. Submit form (có validation, events)

**Test query execution:**

```bash
# SSH vào container
docker-compose exec php bash

# Run tinker
php cli tinker

# Execute query
>>> \Modules\User\Infrastructure\Models\User::count();
```

## 📊 Debug Bar Features

### Realtime Metrics

#### Queries

```
Queries: 5

├─ [12.5ms] SELECT * FROM users WHERE id = ?
├─ [5.2ms] SELECT * FROM roles WHERE user_id = ?
├─ [3.1ms] UPDATE users SET last_login = ? WHERE id = ?
└─ ...
```

**Click để xem:**

- Full SQL query
- Bindings/parameters
- Execution time
- Row count
- Call stack

#### Events

```
Events: 3

├─ User\Events\UserLoggedIn
│  ├─ Payload: { user_id: 1, ip: "127.0.0.1" }
│  └─ Time: 2025-10-26 18:06:05
├─ Core\Events\CacheCleared
└─ ...
```

#### Cache

```
Cache: hits 10, misses 2

├─ HIT: user:1:permissions (redis)
├─ HIT: config:app (redis)
├─ MISS: user:2:profile (redis)
└─ ...
```

#### Session

```
Session: 4 operations

├─ SET: _token = "abc123..."
├─ SET: login_web_xxx = 1
├─ GET: _token
└─ ...
```

### Click To Expand

Mỗi metric có thể click để xem chi tiết:

```
┌─────────────────────────────────┐
│ Queries: 5                  [▼] │
├─────────────────────────────────┤
│                                 │
│ [12.5ms] SELECT * FROM users    │
│                                 │
│ Bindings: [1]                   │
│ Rows: 1                         │
│                                 │
│ Stack Trace:                    │
│   UserRepository.php:45         │
│   UserController.php:23         │
│                                 │
└─────────────────────────────────┘
```

## 🎨 Customization

### Disable Realtime (Chỉ Dùng Fetch)

```php
// resources/views/debug/bar.blade.php
BaultDebugBar.initWebSocket() {
    // Comment out để disable WebSocket
    // this.connectWebSocket();

    // Chỉ dùng fetch mỗi khi có request mới
}
```

### Change Update Interval

```javascript
// Thay đổi interval check updates
BaultDebugBar.startPeriodicFetch() {
    this.fetchInterval = setInterval(() => {
        this.fetchDebugData();
    }, 5000); // 5 giây thay vì 2 giây
}
```

### Custom Collectors

```php
// src/Providers/DebugbarServiceProvider.php
protected function addCollectorsToDebugbar(DebugBar $debugbar): void {
    // Thêm custom collector
    $debugbar->addCollector(new MyCustomCollector());
}
```

## 📚 API Reference

### DebugBroadcaster

```php
interface DebugBroadcaster {
    public function enable(string $requestId): void;
    public function disable(): void;
    public function isEnabled(): bool;

    // Broadcast methods
    public function broadcastQuery(array $data): void;
    public function broadcastEvent(string $name, array $payload): void;
    public function broadcastCache(string $operation, string $key, $value): void;
    public function broadcastSession(string $operation, string $key, $value): void;
    public function broadcastMetrics(float $time, int $memory): void;
}
```

### WebSocket Message Format

```typescript
interface DebugRealtimeMessage {
  type: "debug_realtime";
  payload: {
    type: "query" | "event" | "cache" | "session" | "metrics";
    data: Record<string, any>;
  };
}
```

## ✅ Checklist

Sau khi setup, verify:

- [ ] `APP_DEBUG=true` trong .env
- [ ] Server đã restart
- [ ] Debug bar hiển thị ở bottom của page
- [ ] WebSocket connected (check console)
- [ ] Visit page có queries → Queries count tăng
- [ ] Dispatch event → Events count tăng
- [ ] Cache operation → Cache metrics update
- [ ] Click vào metrics → Chi tiết hiển thị

## 🎉 Kết Quả

Khi setup đúng:

1. ✅ Debug bar luôn visible ở bottom
2. ✅ Metrics cập nhật **realtime** (không cần refresh)
3. ✅ Click để xem chi tiết queries, events, etc
4. ✅ WebSocket connection stable
5. ✅ Performance metrics accurate

**Perfect! 🚀**
