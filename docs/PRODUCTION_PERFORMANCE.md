# Production chậm hơn Dev – Nguyên nhân và tối ưu

## Triệu chứng (wrk)

- **Latency:** ~223ms avg, max 2s, 4707 timeouts trong 60s
- **Throughput:** ~802 req/s với 5 threads, 200 connections
- **Non-2xx/3xx:** 420 responses

Khi so sánh, môi trường **production** (APP_ENV=production, APP_DEBUG=false) có thể chậm hơn **development** vì các điểm dưới đây.

---

## 1. Nguyên nhân thường gặp

### 1.1 Observability / Tracing (OpenTelemetry)

- **Config:** `config/observability.php` – mặc định `OBSERVABILITY_ENABLED=true`, `TRACING_ENABLED=true`, `TRACING_SAMPLE_RATE=1.0`.
- **Ảnh hưởng:** Mỗi request có thể tạo span và export tới OTLP (`OTEL_EXPORTER_OTLP_ENDPOINT`). Nếu endpoint chậm hoặc không chạy, có thể block hoặc tốn CPU.
- **Cách xử lý:** Tắt khi không dùng hoặc giảm sample rate.

```env
# Production: tắt hoặc giảm
OBSERVABILITY_ENABLED=false
# hoặc
TRACING_ENABLED=false
TRACING_SAMPLE_RATE=0.01
```

### 1.2 Session / Cache driver

- **Dev:** thường dùng `SESSION_DRIVER=file`, `CACHE_DRIVER=file` (I/O local).
- **Production:** thường dùng `SESSION_DRIVER=redis`, `CACHE_DRIVER=redis` → thêm round-trip mạng mỗi request.
- **Ảnh hưởng:** Redis vài ms/request; khi kết nối nhiều (pool nhỏ, timeout) dễ tăng latency và timeout.
- **Cách xử lý:** Giữ Redis cho production, nhưng tăng pool và timeout (xem mục 3).

### 1.3 EnsureAdminUserExists

- Middleware dùng **cache** `rememberForever('system.has_admin_user')` (DB count lần đầu, sau đó cache).
- **Production:** cache thường là Redis → mỗi request (trừ route except) có 1 Redis get. Overhead nhỏ nhưng cộng dồn khi load cao.
- Không cần tắt; đảm bảo Redis và pool đủ (xem mục 3).

### 1.4 HttpMetricsMiddleware

- Ghi metric vào Swoole Table (in-memory) mỗi request. Overhead thấp, ít khi là nguyên nhân chính.
- Có thể bỏ qua khi đã tối ưu các mục trên.

### 1.5 Worker / pool dưới tải

- **200 connections**, vài worker: mỗi worker xử lý nhiều request đồng thời, cần đủ connection DB/Redis trong pool.
- **Pool cạn:** request chờ connection → latency tăng, dễ timeout (vd 4707 timeouts).
- **Cách xử lý:** Tăng `DB_POOL_WORKER_SIZE`, `REDIS_POOL_WORKER_SIZE`, và/hoặc `SWOOLE_WORKER_NUM` (xem mục 3).

### 1.6 Swoole log level

- **Production:** `SWOOLE_LOG_WARNING` (ít ghi log).
- **Dev:** `SWOOLE_LOG_INFO` (nhiều log hơn).
- Log ít hơn trong production không làm prod chậm hơn dev; đây không phải nguyên nhân “prod chậm hơn dev”.

### 1.7 File watcher (chỉ Dev)

- Dev bật file watcher (reload khi đổi code). Production không có. Watcher tốn tài nguyên → dev có thể chậm hơn, không phải lý do prod chậm.

---

## 2. Checklist nhanh khi Production chậm hơn Dev

| Kiểm tra | Gợi ý |
|----------|--------|
| OTLP / tracing | Đặt `OBSERVABILITY_ENABLED=false` hoặc `TRACING_SAMPLE_RATE=0` khi không dùng. |
| Redis | Đo latency Redis (`redis-cli --latency`); đảm bảo pool đủ (xem 3). |
| DB pool | Log hoặc metric “pool exhaustion” / wait time; tăng pool size nếu cần. |
| Timeout | Tăng `SWOOLE_MAX_WAIT_TIME` (vd 15–30s) nếu request hợp lệ bị kill. |
| Load test URL | Dùng route đơn giản (vd `/ping`) so sánh dev vs prod trước khi đo route nặng. |

---

## 3. Gợi ý cấu hình cho production / load test

### 3.1 Observability (production)

```env
# Tắt nếu không dùng collector
OBSERVABILITY_ENABLED=false
# Hoặc giữ bật nhưng giảm tải
TRACING_SAMPLE_RATE=0.01
METRICS_ENABLED=true
```

### 3.2 Swoole

```env
# Đủ worker cho I/O-bound (vd 200 connections)
SWOOLE_WORKER_NUM=24
SWOOLE_MAX_WAIT_TIME=15
SWOOLE_MAX_REQUEST=10000
```

### 3.3 Connection pools (khi dùng Redis/DB)

```env
DB_POOL_WORKER_SIZE=64
DB_POOL_TASK_SIZE=24
REDIS_POOL_WORKER_SIZE=64
REDIS_POOL_TASK_SIZE=24
```

### 3.4 Load test với wrk

- So sánh **cùng một URL** (vd `http://localhost:888/ping` hoặc `http://localhost:888/`) trên dev và production.
- Tăng dần connections (vd 50 → 200) để xem khi nào latency/timeout tăng mạnh (pool/worker không đủ).

```bash
wrk -t5 -c200 -d60s http://localhost:888/ping
wrk -t5 -c200 -d60s http://localhost:888/
```

---

## 4. Nâng khả năng chịu tải & giảm timeout

Khi gặp nhiều **timeout** (wrk báo `timeout 4707` hoặc 5xx), ưu tiên theo thứ tự:

### 4.1 Tăng thời gian chờ (Swoole)

Request xử lý lâu (DB/Redis đông) dễ vượt `max_wait_time` → Swoole kill request → timeout.

```env
# config/server.php đọc từ env; mặc định 10s
SWOOLE_MAX_WAIT_TIME=20
```

Chỉ tăng vừa phải (15–30s). Nếu vẫn timeout, cần tăng worker/pool chứ không chỉ thời gian chờ.

### 4.2 Đủ worker để xử lý concurrent

200 connections wrk mà ít worker → mỗi worker xử lý nhiều request, queue dài → latency cao, dễ timeout.

```env
# I/O-bound: 2–4x số nhân CPU (trong Docker thường 4–8 nhân → 16–32)
SWOOLE_WORKER_NUM=24
SWOOLE_TASK_WORKER_NUM=8
```

### 4.3 Tránh pool cạn (DB / Redis)

Request chờ lấy connection từ pool → chờ lâu → timeout. Cần **pool size ≥ số request đồng thời** mỗi worker.

```env
# Mỗi worker có thể xử lý nhiều request đồng thời (coroutine)
DB_POOL_WORKER_SIZE=64
DB_POOL_TASK_SIZE=24
REDIS_POOL_WORKER_SIZE=64
REDIS_POOL_TASK_SIZE=24
```

Công thức thô: `worker_num * (số request đồng thời ước tính mỗi worker)` ≤ tổng pool size. Ví dụ 24 worker × ~3 = 72 → đặt 64–128.

### 4.4 Giảm tải không cần thiết

- **Observability:** Production tắt nếu không dùng: `OBSERVABILITY_ENABLED=false` hoặc `TRACING_SAMPLE_RATE=0`.
- **Session/Cache:** Đã dùng Redis thì giữ; đảm bảo Redis không quá tải (monitor latency).

### 4.5 Nginx (phía trước Swoole)

`docker/nginx/default.conf` đã có `proxy_read_timeout 86400`. Nếu muốn đồng bộ với Swoole (tránh client chờ quá lâu mà backend đã timeout):

```nginx
# Trong location / (tùy chọn)
proxy_connect_timeout 10s;
proxy_send_timeout 30s;
proxy_read_timeout 30s;
```

### 4.6 Timeout: wrk vs framework

| Thành phần | Timeout mặc định | Ghi chú |
|------------|------------------|--------|
| **wrk** | **2 giây** | Mặc định trong wrk; request không nhận response trong 2s → wrk ghi là timeout. Tùy chỉnh: `wrk --timeout 10s ...` |
| **Swoole (framework)** | **60s** (trong `config/server.php`: `SWOOLE_MAX_WAIT_TIME`) | Request xử lý quá 60s → Swoole kill worker request. Cấu hình: `.env` → `SWOOLE_MAX_WAIT_TIME=20` (vd 15–30). |
| **Nginx** | **86400s** (24h) trong `docker/nginx/default.conf` | `proxy_read_timeout 86400`; thường không phải nguyên nhân timeout. |

Khi chạy wrk mà thấy nhiều **Socket errors: timeout**: phần lớn là **wrk timeout 2s** (client bỏ chờ), trong khi backend có thể vẫn đang xử lý (tới `SWOOLE_MAX_WAIT_TIME`). Để giảm timeout trong báo cáo wrk: hoặc tối ưu backend cho response &lt; 2s, hoặc tăng timeout wrk khi test: `wrk -t5 -c200 -d60s --timeout 10s http://localhost:888/`.

### 4.7 Kiểm tra sau khi chỉnh

```bash
# So sánh trước/sau
wrk -t5 -c200 -d60s http://localhost:888/
wrk -t5 -c200 -d60s http://localhost:888/ping
```

Xem: **Latency** (avg/max), **Socket errors: timeout**, **Non-2xx/3xx**. Nếu timeout giảm nhưng latency vẫn cao → tăng thêm worker/pool hoặc tối ưu query/cache.

---

## 5. Tóm tắt

- **Production chậm hơn dev** thường do: tracing, Redis round-trip, pool/worker thiếu.
- **Giảm timeout:** Tăng `SWOOLE_MAX_WAIT_TIME` (15–30s), tăng `SWOOLE_WORKER_NUM` và **pool size** (DB/Redis), tắt observability khi không cần.
- Sau mỗi lần chỉnh, chạy lại wrk và so sánh latency + timeout + non-2xx.
