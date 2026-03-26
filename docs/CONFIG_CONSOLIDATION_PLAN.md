# Config Files Consolidation Plan

## 📊 Phân Tích Các File Config

### Hiện Tại: 49 Files Config

#### Core Configs (Giữ nguyên)
- ✅ `app.php` - Application core
- ✅ `server.php` - Server configuration
- ✅ `database.php` - Database connection
- ✅ `cache.php` - Cache driver
- ✅ `session.php` - Session
- ✅ `auth.php` - Authentication
- ✅ `queue.php` - Queue
- ✅ `mail.php` - Mail
- ✅ `logging.php` - Logging
- ✅ `filesystems.php` - File storage
- ✅ `hashing.php` - Password hashing
- ✅ `view.php` - View engine
- ✅ `events.php` - Events
- ✅ `cors.php` - CORS policy
- ✅ `redis.php` - Redis connection

---

## 🔄 Files Cần Gộp

### 1. **Performance & Optimization → `optimization.php`**

**Gộp 4 files:**
- ❌ `performance.php` (JIT, Request batching)
- ❌ `database-optimization.php` (Database optimization)
- ❌ `read-model-optimization.php` (CQRS read models)
- ❌ `cache-advanced.php` (Multi-tier caching)

**→ Thành 1 file:** ✅ `optimization.php`

**Lý do:**
- Tất cả đều liên quan đến performance optimization
- Dễ quản lý hơn khi tất cả optimization settings ở 1 chỗ
- Tránh confusion giữa các files

**Sections trong file mới:**
```php
'optimization' => [
    'opcache' => [...],      // From performance.php
    'jit' => [...],          // From performance.php
    'request_batching' => [...], // From performance.php
    'database' => [...],     // From database-optimization.php
    'read_models' => [...],  // From read-model-optimization.php
    'cache_advanced' => [...], // From cache-advanced.php
]
```

---

### 2. **Advanced Technologies → `advanced.php`**

**Gộp 6 files:**
- ❌ `database-advanced.php` (Vector, TimeSeries, Graph)
- ❌ `edge-computing.php` (Edge computing)
- ❌ `wasm.php` (WebAssembly)
- ❌ `graphql.php` (GraphQL)
- ❌ `grpc.php` (gRPC)
- ❌ `modern-php.php` (PHP 8.3+)

**→ Thành 1 file:** ✅ `advanced.php`

**Lý do:**
- Tất cả đều là advanced/experimental features
- Thường không dùng trong production thông thường
- Dễ enable/disable tất cả advanced features

**Sections:**
```php
'advanced' => [
    'vector_db' => [...],
    'timeseries_db' => [...],
    'graph_db' => [...],
    'edge_computing' => [...],
    'wasm' => [...],
    'graphql' => [...],
    'grpc' => [...],
    'modern_php' => [...],
]
```

---

### 3. **Streaming & Events → `streaming.php`**

**Gộp 3 files:**
- ❌ `event-streaming.php` (Kafka, RabbitMQ)
- ❌ `realtime-streaming.php` (SSE, WebRTC)
- ❌ `event-sourcing.php` (Event sourcing)

**→ Thành 1 file:** ✅ `streaming.php`

**Lý do:**
- Tất cả liên quan đến event-driven architecture
- Event streaming, real-time, và event sourcing là concepts liên quan

**Sections:**
```php
'streaming' => [
    'event_streaming' => [...], // Kafka, RabbitMQ
    'realtime' => [...],        // SSE, WebRTC
    'event_sourcing' => [...],  // Event sourcing
]
```

---

### 4. **Development & Testing → `development.php`**

**Gộp 3 files:**
- ❌ `development.php` (Development tools)
- ❌ `testing.php` (Testing config)
- ❌ `debug.php` (Debug mode)

**→ Thành 1 file:** ✅ `development.php` (improved)

**Lý do:**
- Tất cả dành cho development environment
- Thường bật trong dev, tắt trong production

**Sections:**
```php
'development' => [
    'tools' => [...],      // From development.php
    'testing' => [...],    // From testing.php
    'debug' => [...],      // From debug.php
]
```

---

### 5. **Security & Monitoring → Giữ Riêng**

**Keep separate vì quan trọng:**
- ✅ `security.php` - Security configurations
- ✅ `observability.php` - OpenTelemetry, Anomaly detection

---

### 6. **CMS Specific → Giữ trong Module**

- ✅ `Modules/Cms/config/cms.php`
- ✅ `Modules/Cms/config/event-sourcing.php`

---

### 7. **Small Configs → Xem xét gộp**

**Có thể gộp:**
- ❌ `cors-origins.php` → Gộp vào `cors.php`
- ❌ `trustedproxy.php` → Gộp vào `security.php`
- ❌ `deduplication.php` → Gộp vào `optimization.php`

---

## 📈 Kết Quả Sau Khi Gộp

### Before: 49 files
### After: 28 files (-21 files, -43%)

**Core: 15 files** (no change)
- app, server, database, cache, session, auth, queue, mail, logging, filesystems, hashing, view, events, cors, redis

**Consolidated: 4 files** (was 13)
- ✅ `optimization.php` (was 4 files)
- ✅ `advanced.php` (was 6 files)
- ✅ `streaming.php` (was 3 files)
- ✅ `development.php` (improved from 3 files)

**Security & Monitoring: 2 files**
- security.php, observability.php

**External Services: 7 files**
- meilisearch, oauth2, graphqlite, profanity, sanitizer, sentry, uploads

---

## 🎯 Implementation Plan

### Priority 1: Optimization Configs

1. Create `config/optimization.php`
2. Migrate content from:
   - performance.php
   - database-optimization.php
   - read-model-optimization.php
   - cache-advanced.php
   - deduplication.php
3. Update code references
4. Delete old files

### Priority 2: Advanced Features

1. Create `config/advanced.php`
2. Migrate content from:
   - database-advanced.php
   - edge-computing.php
   - wasm.php
   - graphql.php (partial)
   - grpc.php
   - modern-php.php
3. Update code references
4. Delete old files

### Priority 3: Streaming

1. Create `config/streaming.php`
2. Migrate content from:
   - event-streaming.php
   - realtime-streaming.php
   - event-sourcing.php (core)
3. Update code references
4. Delete old files

### Priority 4: Development

1. Improve `config/development.php`
2. Migrate content from:
   - testing.php
   - debug.php
3. Update code references
4. Delete old files

### Priority 5: Small Consolidations

1. Merge `cors-origins.php` → `cors.php`
2. Merge `trustedproxy.php` → `security.php`

---

## ⚠️ Breaking Changes

### Code Changes Required

**Before:**
```php
config('performance.jit_optimization.enabled')
config('database-optimization.adaptive_pool.enabled')
config('cache-advanced.multi_tier.enabled')
```

**After:**
```php
config('optimization.jit.enabled')
config('optimization.database.adaptive_pool.enabled')
config('optimization.cache.multi_tier.enabled')
```

### Migration Strategy

1. **Create new files first**
2. **Update code to use new config paths**
3. **Keep old files for 1 version (deprecated)**
4. **Add deprecation warnings**
5. **Remove old files in next major version**

---

## 📝 New Config Structure

```
config/
├── Core (15 files)
│   ├── app.php
│   ├── server.php
│   ├── database.php
│   ├── cache.php
│   ├── session.php
│   ├── auth.php
│   ├── queue.php
│   ├── mail.php
│   ├── logging.php
│   ├── filesystems.php
│   ├── hashing.php
│   ├── view.php
│   ├── events.php
│   ├── cors.php
│   └── redis.php
│
├── Optimization (1 file) ✨ NEW
│   └── optimization.php
│       ├── opcache
│       ├── jit
│       ├── request_batching
│       ├── database
│       ├── read_models
│       ├── cache_advanced
│       └── deduplication
│
├── Advanced (1 file) ✨ NEW
│   └── advanced.php
│       ├── vector_db
│       ├── timeseries_db
│       ├── graph_db
│       ├── edge_computing
│       ├── wasm
│       ├── graphql
│       ├── grpc
│       └── modern_php
│
├── Streaming (1 file) ✨ NEW
│   └── streaming.php
│       ├── event_streaming
│       ├── realtime
│       └── event_sourcing
│
├── Development (1 file) ✨ IMPROVED
│   └── development.php
│       ├── tools
│       ├── testing
│       └── debug
│
├── Security & Monitoring (2 files)
│   ├── security.php (+ trustedproxy)
│   └── observability.php
│
└── External Services (7 files)
    ├── meilisearch.php
    ├── oauth2.php
    ├── graphqlite.php
    ├── profanity.php
    ├── sanitizer.php
    ├── sentry.php
    ├── uploads.php
    └── features.php
```

---

## ✅ Benefits

1. **Reduced Complexity**
   - 49 files → 28 files (-43%)
   - Easier to find related settings

2. **Better Organization**
   - Logical grouping by functionality
   - Clear separation of concerns

3. **Easier Maintenance**
   - One place for all optimization settings
   - Easier to enable/disable feature groups

4. **Better Documentation**
   - Clearer file purposes
   - Better discoverability

5. **Simplified Deployment**
   - Fewer files to manage
   - Easier environment-specific configs

---

## 🚀 Next Steps

Bạn muốn tôi:
1. ✅ **Implement Priority 1** - Tạo `optimization.php`?
2. ✅ **Implement All Priorities** - Gộp tất cả ngay?
3. 📋 **Review Plan First** - Xem lại plan trước?

Tôi sẽ implement cẩn thận với backward compatibility!
