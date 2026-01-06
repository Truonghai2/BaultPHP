# Queue System Testing Guide

## Kiểm tra Queue System có hoạt động không

### 1. Kiểm tra Configuration

**File**: `config/queue.php`

```php
'default' => env('QUEUE_CONNECTION', 'sync'),
```

**File**: `.env`

```bash
QUEUE_CONNECTION=redis  # Hoặc database, sync, rabbitmq
```

**Drivers hỗ trợ**:

- `sync` - Chạy job ngay lập tức (không asynchronous)
- `database` - Queue jobs trong MySQL
- `redis` - Queue jobs trong Redis (recommended)
- `rabbitmq` - Queue jobs trong RabbitMQ
- `swoole` - Swoole coroutine queue

---

### 2. Kiểm tra Queue Worker

#### Check xem worker có đang chạy không:

```bash
# Trong container
ps aux | grep "queue:work"

# Hoặc check processes
docker exec bault_app ps aux | grep queue
```

#### Nếu không có worker:

```bash
# Chạy worker manually (foreground)
docker exec -it bault_app php cli queue:work

# Hoặc chạy background
docker exec -d bault_app php cli queue:work
```

---

### 3. Test Job Dispatch

#### Method 1: Sử dụng Test Command

```bash
# Test với module Test
docker exec bault_app php cli queue:test

# Test với module cụ thể
docker exec bault_app php cli queue:test Admin
```

#### Method 2: Test Manual

```bash
docker exec bault_app php cli tinker
```

```php
// Trong tinker
dispatch(new \Modules\Admin\Application\Jobs\InstallModuleDependenciesJob('Test'));
exit;
```

---

### 4. Kiểm tra Jobs trong Database/Redis

#### Database Driver

```sql
-- Xem jobs pending
SELECT * FROM jobs ORDER BY id DESC LIMIT 10;

-- Xem failed jobs
SELECT * FROM failed_jobs ORDER BY id DESC LIMIT 10;
```

```bash
docker exec bault_db mysql -u root -psecret bault -e "SELECT * FROM jobs;"
docker exec bault_db mysql -u root -psecret bault -e "SELECT * FROM failed_jobs;"
```

#### Redis Driver

```bash
# Connect to Redis
docker exec -it bault_redis redis-cli

# Check queues
KEYS queues:*

# Check queue length
LLEN queues:default

# View jobs in queue
LRANGE queues:default 0 -1

# View delayed jobs
ZRANGE queues:delayed:default 0 -1 WITHSCORES
```

---

### 5. Kiểm tra Logs

```bash
# Real-time logs
docker exec bault_app tail -f /app/storage/logs/app.log | grep -E "Job|Queue|Dependencies"

# Check specific job logs
docker exec bault_app grep "InstallModuleDependencies" /app/storage/logs/app.log
```

**Expected logs khi job chạy**:

```
[INFO] Processing job: InstallModuleDependenciesJob
[INFO] Installing dependencies for module: Test
[INFO] Job completed successfully
```

---

### 6. Test Flow hoàn chỉnh

#### Step 1: Clear queues

```bash
# Redis
docker exec bault_redis redis-cli FLUSHDB

# Database
docker exec bault_db mysql -u root -psecret bault -e "TRUNCATE TABLE jobs;"
```

#### Step 2: Dispatch test job

```bash
docker exec bault_app php cli queue:test Test
```

#### Step 3: Verify job trong queue

**Redis**:

```bash
docker exec bault_redis redis-cli LLEN queues:default
# Output: (integer) 1  ← Có 1 job pending
```

**Database**:

```bash
docker exec bault_db mysql -u root -psecret bault -e "SELECT COUNT(*) FROM jobs;"
# Output: 1
```

#### Step 4: Chạy worker

```bash
docker exec bault_app php cli queue:work --once
```

**Expected output**:

```
[2025-10-26 06:00:00] Processing: InstallModuleDependenciesJob
[2025-10-26 06:00:05] Processed:  InstallModuleDependenciesJob
```

#### Step 5: Verify job đã xử lý

**Redis**:

```bash
docker exec bault_redis redis-cli LLEN queues:default
# Output: (integer) 0  ← Queue empty
```

**Database**:

```bash
docker exec bault_db mysql -u root -psecret bault -e "SELECT COUNT(*) FROM jobs;"
# Output: 0  ← Job đã được process
```

---

### 7. Troubleshooting

#### ❓ Job không được dispatch

**Check 1: Queue connection**

```bash
docker exec bault_app php -r "echo env('QUEUE_CONNECTION');"
```

**Check 2: Redis connection**

```bash
docker exec bault_redis redis-cli PING
# Expected: PONG
```

**Check 3: Job class exists**

```bash
docker exec bault_app php -r "class_exists('\Modules\Admin\Application\Jobs\InstallModuleDependenciesJob') ? print 'OK' : print 'NOT FOUND';"
```

#### ❓ Worker không xử lý jobs

**Check 1: Worker có chạy không**

```bash
ps aux | grep "queue:work"
```

**Check 2: Logs có lỗi không**

```bash
tail -f storage/logs/app.log
```

**Check 3: Failed jobs**

```bash
docker exec bault_app php cli queue:failed
```

#### ❓ Job failed

**Xem chi tiết failed job**:

```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 1\G
```

**Retry failed job**:

```bash
# Retry job by ID
docker exec bault_app php cli queue:retry 1

# Retry all failed jobs
docker exec bault_app php cli queue:retry all
```

**Forget failed job**:

```bash
docker exec bault_app php cli queue:forget 1
```

**Flush all failed jobs**:

```bash
docker exec bault_app php cli queue:flush
```

---

### 8. Production Setup

#### Supervisor Configuration

**File**: `/etc/supervisor/conf.d/queue-worker.conf`

```ini
[program:bault-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /app/cli queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/app/storage/logs/queue-worker.log
stopwaitsecs=3600
```

**Reload supervisor**:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start bault-queue-worker:*
```

#### Docker Compose (Separate Worker Container)

**File**: `docker-compose.yml`

```yaml
services:
  queue-worker:
    image: bault-php-app:latest
    container_name: bault_queue_worker
    command: php /app/cli queue:work --sleep=3 --tries=3
    depends_on:
      - redis
      - db
    volumes:
      - ./:/app
    networks:
      - bault_network
    restart: unless-stopped
```

---

### 9. Queue Worker Options

```bash
# Basic
php cli queue:work

# With options
php cli queue:work \
  --queue=default,high,low \    # Process multiple queues
  --sleep=3 \                   # Sleep 3 seconds between jobs
  --tries=3 \                   # Max 3 attempts
  --timeout=60 \                # 60 second timeout
  --max-time=3600 \             # Run for 1 hour then restart
  --max-jobs=1000 \             # Process 1000 jobs then restart
  --once                        # Process single job then exit
```

---

### 10. Monitoring

#### Real-time monitoring script

```bash
#!/bin/bash
# monitor-queue.sh

while true; do
    clear
    echo "=== Queue Status at $(date) ==="
    echo ""

    echo "📊 Jobs in Queue:"
    docker exec bault_redis redis-cli LLEN queues:default
    echo ""

    echo "❌ Failed Jobs:"
    docker exec bault_db mysql -u root -psecret bault -e "SELECT COUNT(*) FROM failed_jobs;"
    echo ""

    echo "🔄 Recent Logs:"
    docker exec bault_app tail -5 /app/storage/logs/app.log

    sleep 5
done
```

**Run**:

```bash
chmod +x monitor-queue.sh
./monitor-queue.sh
```

---

### 11. Performance Metrics

```php
// Get queue size
$size = Queue::size('default');

// Get job count by status
$pending = DB::table('jobs')->count();
$failed = DB::table('failed_jobs')->count();

// Average processing time
$avgTime = DB::table('jobs')
    ->avg(DB::raw('reserved_at - available_at'));
```

---

## Quick Commands

```bash
# Test queue
docker exec bault_app php cli queue:test

# Run worker (foreground)
docker exec -it bault_app php cli queue:work

# Run worker (background)
docker exec -d bault_app php cli queue:work

# Process one job
docker exec bault_app php cli queue:work --once

# Check queue size (Redis)
docker exec bault_redis redis-cli LLEN queues:default

# Check failed jobs
docker exec bault_app php cli queue:failed

# Retry all failed jobs
docker exec bault_app php cli queue:retry all

# Monitor logs
docker exec bault_app tail -f /app/storage/logs/app.log | grep Job
```

---

## Expected Behavior

### ✅ Working Queue System

```
1. Dispatch job → Job added to queue
2. Worker picks job → Job processing
3. Job completes → Job removed from queue
4. Logs show success → All good!
```

### ❌ Broken Queue System

```
1. Dispatch job → No error
2. Check queue → Job stuck
3. Worker runs → No processing
4. Logs empty → Something wrong!
```

**Fix**: Check connection, restart worker, check logs for errors.

---

**Related docs**:

- [Module Composer Dependencies](./module-composer-dependencies.md)
- [Module Version Management](./module-version-management.md)
