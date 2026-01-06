# Session Database Driver - Phân Tích & Tối Ưu

## Hiện Trạng

### Schema Table `sessions`

```sql
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    lifetime INT NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
);
```

### Session Handler Implementation

**Files**:

- `SwoolePdoSessionHandler.php`
- `SwooleCompatiblePdoSessionHandler.php`

### Write Operation (Mỗi Request)

```sql
INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity, lifetime)
VALUES (?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    payload = VALUES(payload),
    last_activity = VALUES(last_activity),
    lifetime = VALUES(lifetime),
    user_id = VALUES(user_id),
    ip_address = VALUES(ip_address),
    user_agent = VALUES(user_agent)
```

## Vấn Đề Hiện Tại

### 1. **Write Performance** ⚠️

**Vấn đề**: Mỗi request đều phải write session, cả khi không có thay đổi

**Impact**:

- Write operation trên mỗi request (thậm chí read-only requests)
- Lock contention trên PRIMARY KEY
- Overhead khi update `user_agent` (TEXT column)
- Payload luôn được update (LONGTEXT column)

### 2. **Payload Size** ⚠️

**Vấn đề**: `payload` là LONGTEXT và được serialize/unserialize mỗi lần

**Impact**:

- CPU overhead cho serialize/unserialize
- Network bandwidth giữa app và database
- Memory usage cao

### 3. **Garbage Collection** ⚠️

**Vấn đề**: GC query quét toàn bộ table theo `last_activity`

```sql
DELETE FROM sessions WHERE last_activity < ?
```

**Impact**:

- Full table scan nếu không có nhiều sessions hết hạn
- Table lock trong quá trình delete
- Có thể slow down các requests khác

### 4. **Unnecessary Updates** ⚠️

**Vấn đề**: Update `user_id`, `ip_address`, `user_agent` mỗi lần ngay cả khi không thay đổi

## Tối Ưu Hóa

### 1. Schema Optimization

#### A. Tối Ưu Data Types

```sql
CREATE TABLE sessions_optimized (
    id CHAR(40) PRIMARY KEY,                    -- Fixed length (SHA-1 = 40 chars)
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,                 -- IPv6 max = 45 chars
    user_agent VARCHAR(512) NULL,                -- Limit size
    payload MEDIUMBLOB NOT NULL,                 -- Binary instead of TEXT
    last_activity INT UNSIGNED NOT NULL,
    lifetime MEDIUMINT UNSIGNED NOT NULL,        -- Max ~16M seconds (~194 days)
    created_at INT UNSIGNED NOT NULL,            -- Track creation time

    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),
    INDEX idx_created_at_activity (created_at, last_activity)  -- Composite index for cleanup
) ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED          -- Compress rows
  KEY_BLOCK_SIZE=8;               -- 8KB blocks
```

**Benefits**:

- ✅ `CHAR(40)` faster than `VARCHAR(255)` (fixed length)
- ✅ `user_agent` từ TEXT → VARCHAR(512) reduces overhead
- ✅ `payload` từ LONGTEXT → MEDIUMBLOB (binary, ~16MB max)
- ✅ `lifetime` từ INT → MEDIUMINT saves 1 byte per row
- ✅ `COMPRESSED` row format reduces disk I/O
- ✅ Composite index tăng tốc cleanup query

#### B. Partitioning (Optional - Large Scale)

```sql
CREATE TABLE sessions_partitioned (
    ...
    last_activity INT UNSIGNED NOT NULL,
    ...
) PARTITION BY RANGE (last_activity) (
    PARTITION p0 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
    PARTITION p1 VALUES LESS THAN (UNIX_TIMESTAMP('2025-02-01')),
    PARTITION p2 VALUES LESS THAN (UNIX_TIMESTAMP('2025-03-01')),
    ...
    PARTITION pfuture VALUES LESS THAN MAXVALUE
);
```

**Benefits**:

- ✅ Garbage collection chỉ scan partition cũ
- ✅ Drop partition thay vì DELETE (instant cleanup)
- ✅ Query performance tốt hơn với partition pruning

### 2. Write Optimization

#### A. Conditional Update (Smart Write)

```php
public function write(string $sessionId, string $data): bool
{
    return $this->withConnection(function (PDO $pdo) use ($sessionId, $data) {
        // Check if session exists and needs update
        $checkSql = "SELECT payload, last_activity, user_id
                     FROM {$this->table}
                     WHERE id = :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':id', $sessionId);
        $checkStmt->execute();
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $currentTime = time();
        $minUpdateInterval = 60; // Update mỗi 60 giây

        // Skip write nếu:
        // 1. Session tồn tại
        // 2. Payload không đổi
        // 3. Last activity update gần đây (< 60s)
        if ($existing &&
            $existing['payload'] === $data &&
            ($currentTime - $existing['last_activity']) < $minUpdateInterval) {
            return true; // No need to update
        }

        // Extract user_id only if needed
        $userId = $this->extractUserId($data);

        // Update only if needed
        if ($existing) {
            // UPDATE only changed fields
            $updateSql = "UPDATE {$this->table}
                         SET payload = :data,
                             last_activity = :time";

            // Only update user_id if changed
            if ($userId !== $existing['user_id']) {
                $updateSql .= ", user_id = :user_id";
            }

            $updateSql .= " WHERE id = :id";

            $stmt = $pdo->prepare($updateSql);
            $stmt->bindParam(':id', $sessionId);
            $stmt->bindParam(':data', $data, PDO::PARAM_LOB);
            $stmt->bindValue(':time', $currentTime, PDO::PARAM_INT);

            if ($userId !== $existing['user_id']) {
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }

            return $stmt->execute();
        } else {
            // INSERT new session
            return $this->insertSession($pdo, $sessionId, $data, $userId, $currentTime);
        }
    });
}
```

**Benefits**:

- ✅ Giảm 60-80% database writes (chỉ update khi cần)
- ✅ Giảm lock contention
- ✅ Giảm bandwidth

#### B. Batch Garbage Collection

```php
public function gc(int $maxLifetime): int|false
{
    return $this->withConnection(function (PDO $pdo) use ($maxLifetime) {
        // Use LIMIT để cleanup từng batch nhỏ
        $batchSize = 1000;
        $totalDeleted = 0;
        $cutoffTime = time() - $maxLifetime;

        do {
            $sql = "DELETE FROM {$this->table}
                    WHERE last_activity < :time
                    ORDER BY last_activity
                    LIMIT :limit";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':time', $cutoffTime, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
            $stmt->execute();

            $deleted = $stmt->rowCount();
            $totalDeleted += $deleted;

            // Small sleep to avoid blocking other queries
            if ($deleted === $batchSize) {
                usleep(10000); // 10ms
            }

        } while ($deleted === $batchSize);

        return $totalDeleted;
    });
}
```

**Benefits**:

- ✅ Không lock table lâu
- ✅ Không block user requests
- ✅ Incremental cleanup

### 3. Read Optimization

#### A. Add Column for Payload Size Tracking

```sql
ALTER TABLE sessions
ADD COLUMN payload_size INT UNSIGNED DEFAULT 0 AFTER payload,
ADD INDEX idx_payload_size (payload_size);
```

```php
public function write(...) {
    $payloadSize = strlen($data);

    // Only warn if > 100KB
    if ($payloadSize > 102400) {
        Log::warning("Large session payload", [
            'session_id' => $sessionId,
            'size' => $payloadSize,
        ]);
    }

    // Store size for monitoring
    $sql = "INSERT ... payload_size = :size ...";
}
```

**Benefits**:

- ✅ Monitor session size issues
- ✅ Identify problematic sessions
- ✅ Alert on bloated sessions

#### B. Lazy Load Request Attributes

Không extract `user_id`, `ip_address`, `user_agent` mỗi lần write:

```php
private function shouldUpdateMetadata(array $existing): bool
{
    // Only update metadata mỗi 5 phút
    return ($existing['last_activity'] % 300) === 0;
}
```

### 4. Caching Layer (Optional - High Traffic)

#### A. Redis Cache + Database Persistence

```
┌─────────────────────────────────────────┐
│   Request → Session Read                │
└─────────────────┬───────────────────────┘
                  │
                  ▼
         ┌────────────────┐
         │  Redis Cache?  │
         └────────┬───────┘
                  │
        ┌─────────┴─────────┐
        │ HIT              MISS
        ▼                   ▼
   ┌─────────┐      ┌──────────────┐
   │ Return  │      │ Read from DB │
   └─────────┘      └──────┬───────┘
                           │
                           ▼
                    ┌─────────────┐
                    │ Store Redis │
                    └─────────────┘
```

**Implementation**:

```php
class CachedSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private SessionHandlerInterface $dbHandler,
        private SwooleRedisPool $redisPool,
        private int $cacheTtl = 600,
    ) {}

    public function read(string $sessionId): string|false
    {
        // Try Redis first
        $cached = $this->redisPool->get("session:$sessionId");
        if ($cached !== false) {
            return $cached;
        }

        // Fallback to database
        $data = $this->dbHandler->read($sessionId);

        if ($data !== false) {
            // Cache for next read
            $this->redisPool->setex("session:$sessionId", $this->cacheTtl, $data);
        }

        return $data;
    }

    public function write(string $sessionId, string $data): bool
    {
        // Write to Redis immediately
        $this->redisPool->setex("session:$sessionId", $this->cacheTtl, $data);

        // Write to DB async (optional)
        // Or write every N seconds
        return $this->dbHandler->write($sessionId, $data);
    }
}
```

**Benefits**:

- ✅ 95%+ reads từ Redis (fast)
- ✅ Database chỉ cho persistence
- ✅ Giảm database load dramatically

## Migration Plan

### Phase 1: Non-Breaking Optimizations

```sql
-- 1. Add indexes
ALTER TABLE sessions
ADD INDEX idx_created_at_activity (last_activity, user_id);

-- 2. Optimize columns
ALTER TABLE sessions
MODIFY COLUMN user_agent VARCHAR(512) NULL,
MODIFY COLUMN lifetime MEDIUMINT UNSIGNED NOT NULL;

-- 3. Add monitoring column
ALTER TABLE sessions
ADD COLUMN payload_size INT UNSIGNED DEFAULT 0 AFTER payload;

-- 4. Enable compression (requires rebuild)
ALTER TABLE sessions
ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;
```

### Phase 2: Code Optimization

```php
// 1. Deploy smart write logic
// 2. Deploy batch GC
// 3. Monitor performance
```

### Phase 3: Redis Caching (Optional)

```php
// 1. Setup Redis pool
// 2. Deploy CachedSessionHandler
// 3. Gradual rollout (A/B test)
```

## Benchmarks

### Current Performance (Estimated)

```
Write operations: ~500/sec per server
Average write time: 5-10ms
Database CPU: 40-60%
Lock wait time: 100-500ms (peak)
```

### After Optimization (Expected)

```
Write operations: ~200/sec (60% reduction)
Average write time: 2-5ms (50% faster)
Database CPU: 20-30% (50% reduction)
Lock wait time: 10-50ms (90% reduction)
```

### With Redis Cache (Expected)

```
Write operations: ~50/sec to DB (90% reduction)
Read from Redis: ~5000/sec
Average read time: <1ms
Database CPU: <10%
```

## Monitoring

### Key Metrics to Track

```sql
-- Session count by age
SELECT
    CASE
        WHEN last_activity > UNIX_TIMESTAMP() - 300 THEN 'active (< 5 min)'
        WHEN last_activity > UNIX_TIMESTAMP() - 1800 THEN 'recent (< 30 min)'
        WHEN last_activity > UNIX_TIMESTAMP() - 3600 THEN 'idle (< 1 hour)'
        ELSE 'stale (> 1 hour)'
    END AS status,
    COUNT(*) as count,
    AVG(LENGTH(payload)) as avg_payload_size,
    MAX(LENGTH(payload)) as max_payload_size
FROM sessions
GROUP BY status;

-- Top users by session count
SELECT user_id, COUNT(*) as session_count
FROM sessions
WHERE user_id IS NOT NULL
GROUP BY user_id
ORDER BY session_count DESC
LIMIT 20;

-- Payload size distribution
SELECT
    CASE
        WHEN LENGTH(payload) < 1024 THEN '< 1KB'
        WHEN LENGTH(payload) < 10240 THEN '1-10KB'
        WHEN LENGTH(payload) < 102400 THEN '10-100KB'
        ELSE '> 100KB'
    END AS size_range,
    COUNT(*) as count
FROM sessions
GROUP BY size_range;
```

### Slow Query Log

```ini
# my.cnf
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 0.5

# Monitor for:
# - UPDATE sessions taking > 500ms
# - DELETE (GC) taking > 5s
```

## Best Practices

### 1. Keep Payload Small

```php
// ❌ Bad - Store large data in session
session(['large_array' => range(1, 10000)]);

// ✅ Good - Store reference, cache actual data
session(['cached_key' => $cacheKey]);
Cache::put($cacheKey, $largeData);
```

### 2. Limit Session Lifetime

```php
// config/session.php
'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours

// Don't use:
'lifetime' => 43200, // 30 days is too long
```

### 3. Implement Session Regeneration

```php
// After login
session()->regenerate();

// Periodically
if (session()->get('last_regeneration') < time() - 3600) {
    session()->regenerate();
    session()->set('last_regeneration', time());
}
```

### 4. Monitor & Alert

```php
// Log large sessions
if (strlen(session()->all()) > 102400) {
    Log::warning('Large session detected', [
        'user_id' => auth()->id(),
        'size' => strlen(session()->all()),
    ]);
}
```

## Conclusion

### Priority Implementation Order

1. **High Priority** (Immediate):
   - Add composite index
   - Optimize column types
   - Implement smart write logic

2. **Medium Priority** (Within 1 month):
   - Implement batch GC
   - Add payload size monitoring
   - Enable row compression

3. **Low Priority** (Future):
   - Redis caching layer
   - Table partitioning
   - Advanced monitoring dashboard

### Expected Results

After implementing High + Medium priority optimizations:

- ✅ 60-70% reduction in database writes
- ✅ 50% faster write operations
- ✅ 80% reduction in lock contention
- ✅ Better scalability for high traffic

With Redis caching:

- ✅ 90% reduction in database load
- ✅ 10x faster session read operations
- ✅ Can handle 10x more concurrent users
