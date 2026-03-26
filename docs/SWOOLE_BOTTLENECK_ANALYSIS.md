# Phân tích điểm nghẽn Swoole (Bottleneck Analysis)

## Đã áp dụng (Implemented)

- **Ổn định toàn hệ thống (mọi path):**
  - **Nhóm middleware `light`:** Route `/ping`, `/api/health`, `/health`, `/health/*`, `/metrics` dùng group `light` → chỉ chạy CorrelationId + ParseBody + SubstituteBindings (không session, CSRF, EnsureAdmin, CheckForPendingModules). Các controller: `HealthCheckController`, `HealthController`, `MetricsController` có `#[Route(group: 'light')]`.
  - **CheckForPendingModules:** Thoát ngay nếu path không bắt đầu bằng `/admin` → không gọi `auth()->user()` cho phần lớn request.
  - **EnsureAdminUserExists:** Except thêm `api*`, `assets*` → toàn bộ API và assets bỏ qua check admin.
  - **Redis bắt buộc khi Swoole:** Session và cache dùng Redis → mọi path đều không bị blocking I/O (file).
- **Mục tiêu ~2000 req/s:** `HomePageCacheMiddleware` chạy đầu pipeline: GET / không session → trả ngay từ cache. Cấu hình Swoole: `worker_num`/`reactor_num` tối thiểu 8, khuyến nghị 16; `.env.example`: `SWOOLE_WORKER_NUM=16`, `SWOOLE_REACTOR_NUM=16`.
- **Session & Cache:** `.env.example` và config khuyến nghị `SESSION_DRIVER=redis`, `CACHE_STORE=redis`. `config/cache.php` đọc `CACHE_STORE`/`CACHE_DRIVER` từ env.
- **Cache trang chủ (GET /):** Guest được cache full HTML 60s (`page.home.guest`). Cache hit trả về ngay (từ middleware hoặc controller). Xóa cache khi cập nhật trang chủ: `cache(null)->forget('page.home.guest');`
- **Clockwork:** Chỉ đăng ký khi `APP_DEBUG=true` hoặc `CLOCKWORK_ENABLED=true` (`ClockworkServiceProvider`).
- **PerformanceMonitoringMiddleware:** Chỉ đo và log khi `APP_DEBUG=true`; production bỏ qua hoàn toàn.
- **Tài liệu:** Cập nhật `.env.example` với comment Swoole; thêm gợi ý trong `config/session.php` và `config/cache.php`.

---

Dựa trên kết quả benchmark `wrk -t8 -c100 -d30s http://localhost:888/`:
- **96 timeout errors** – nhiều request vượt quá timeout của wrk (mặc định ~1–2s)
- **Latency cao, độ lệch chuẩn lớn** (avg ~130ms, stdev ~319ms, max 1.26s) – xử lý không ổn định
- **Requests/sec thực tế ~20** – throughput thấp so với 100 kết nối đồng thời

Dưới đây là các điểm nghẽn chính và hướng xử lý.

---

## 1. Session driver = File (blocking I/O)

**Vấn đề:** Mặc định `SESSION_DRIVER=file`. Mỗi request đều gọi `StartSession` → đọc/ghi file session. File I/O trong Swoole là **blocking**, nên coroutine không yield, worker bị block → hàng đợi request dài, latency tăng, dễ timeout.

**Khuyến nghị:**
- Dùng **Redis** cho session khi chạy Swoole: `SESSION_DRIVER=redis`
- Redis dùng connection pool (coroutine-friendly), giảm tranh chấp và blocking.

```env
SESSION_DRIVER=redis
CACHE_STORE=redis   # hoặc CACHE_DRIVER=redis (config/cache.php đọc CACHE_STORE hoặc CACHE_DRIVER)
```

---

## 2. Middleware EnsureAdminUserExists – Cache/DB mỗi request

**Vấn đề:** Middleware này chạy trên hầu hết request (trừ vài route except). Dùng `rememberForever('system.has_admin_user')` hoặc fallback query DB. Nếu cache driver là **file**, mỗi request vẫn phải đọc cache → blocking I/O. Cache miss hoặc cold start → query DB mỗi request.

**Khuyến nghị:**
- Dùng Redis (hoặc driver không block) cho cache: `CACHE_STORE=redis`
- Giữ nguyên logic, chỉ cần đảm bảo cache/session không dùng file khi chạy Swoole.

---

## 3. Trang chủ (GET /) – Nhiều query DB + view nặng

**Vấn đề:** `PageViewController::home()` có thể thực hiện **tới 3 query** (slug home → name Home → orderBy id), mỗi query đều `with(['blocks' => ..., 'blockType'])`. Sau đó render view `pages.show` (compile Blade, nhiều block). Dưới tải cao, DB và CPU đều có thể trở thành nghẽn.

**Khuyến nghị:**
- **Cache trang chủ** cho user chưa đăng nhập (full page cache hoặc cache HTML), TTL ngắn (vd: 60s).
- Gộp logic lấy page: một query với `whereIn('slug', ['home'])` hoặc `orderBy` thay vì 3 lần query.
- Pre-compile view (vd: `php cli view:cache`) để giảm compile Blade lúc chạy.

---

## 4. Quá nhiều middleware trên mỗi request

**Vấn đề:** Mỗi request đi qua **9 global middleware** + **8 middleware nhóm web** (EncryptCookies, StartSession, VerifyCsrfToken, CheckForPendingModules, v.v.). Mỗi middleware thêm một chút CPU và có thể I/O (session, cache, auth). Tổng chi phí đáng kể khi throughput cao.

**Khuyến nghị:**
- Tắt hoặc chỉ bật khi cần: **ClockworkMiddleware** (chỉ khi debug), **PerformanceMonitoringMiddleware** (hoặc chỉ log khi > ngưỡng).
- Đối với route benchmark/health: dùng route riêng **không** qua nhóm `web` (không session, CSRF, pending modules) để đo “raw” throughput.
- Cân nhắc tách route tĩnh/API ra middleware group nhẹ hơn (ít middleware hơn).

---

## 5. Số worker và connection pool

**Vấn đề:** `worker_num = swoole_cpu_num() * 4` (vd: 8–16 worker). Mỗi worker xử lý nhiều request nhờ coroutine, nhưng nếu có **blocking I/O** (session file, cache file) thì worker vẫn bị block → hiệu quả giảm.

**Khuyến nghị:**
- Đã dùng connection pool DB/Redis → giữ hoặc tăng nhẹ `worker_num` nếu CPU còn trống (tránh quá nhiều worker gây context switch).
- Đảm bảo **DB pool size** đủ: `DB_POOL_WORKER_SIZE` (mặc định 50/worker). Tổng connection ≈ worker_num × pool_size; không nên nhỏ hơn số kết nối đồng thời cần thiết.

---

## 6. Timeout của wrk

**Vấn đề:** wrk mặc định timeout khá thấp (thường 1–2s). Request chậm do session file, DB hoặc CPU sẽ bị wrk tính là **timeout** (96 lỗi trong benchmark), dù server vẫn xử lý xong sau đó.

**Khuyến nghị:**
- Tăng timeout khi benchmark để giảm timeout ảo và thấy rõ throughput/latency thực:  
  `wrk -t8 -c100 -d30s --timeout 10s http://localhost:888/`
- Sau khi tối ưu (Redis session/cache, cache trang chủ, giảm middleware không cần), chạy lại wrk để so sánh.

---

## 7. Thứ tự ưu tiên tối ưu

| Ưu tiên | Hành động | Tác động ước tính |
|--------|-----------|--------------------|
| 1 | **SESSION_DRIVER=redis**, **CACHE_STORE=redis** | Giảm blocking I/O mạnh, giảm latency và timeout |
| 2 | Cache trang chủ (GET /) cho anonymous, TTL 60s | Giảm DB + view cho phần lớn request tới home |
| 3 | Tắt Clockwork / PerformanceMonitoring trên production hoặc chỉ bật khi debug | Giảm CPU và I/O mỗi request |
| 4 | Tăng wrk timeout khi benchmark (vd 10s) | Đo chính xác hơn, ít “timeout ảo” |
| 5 | Gộp/giảm query và pre-compile view cho home | Giảm DB và CPU khi cache miss |

---

## 8. Kiểm tra nhanh sau khi đổi config

```bash
# Session + cache dùng Redis
grep -E "SESSION_DRIVER|CACHE_STORE" .env

# Benchmark với timeout cao hơn
wrk -t8 -c100 -d30s --timeout 10s http://localhost:888/

# So sánh với route nhẹ (không session)
wrk -t8 -c100 -d30s --timeout 10s http://localhost:888/ping
```

Nếu route `/ping` có throughput cao, latency thấp còn `/` vẫn nặng → nghẽn chủ yếu ở xử lý trang chủ + session/cache; tiếp tục áp dụng Redis và cache trang chủ như trên.

---

## 9. Phương án đạt 2000 req/s (từ ~500 req/s)

| Thành phần | Mô tả |
|------------|--------|
| **Fast path trong Kernel** | Trước khi dispatch route và build pipeline: nếu GET `/`, guest (không có session cookie), thử `cache()->get('page.home.guest')`. Hit → return response ngay, **không** chạy router, không build pipeline, không chạy bất kỳ middleware nào. |
| **L1 per-worker** | Trong `HomePageCacheMiddleware` (khi request đi qua middleware): sau khi lấy từ Redis, lưu HTML vào static (TTL 60s). Request sau trong cùng worker dùng L1 → header `X-Cache: HIT-L1`, không gọi Redis. |
| **Cấu hình** | `SESSION_DRIVER=redis`, `CACHE_STORE=redis`. `.env`: `SWOOLE_WORKER_NUM=32`, `SWOOLE_REACTOR_NUM=32`, `DB_POOL_WORKER_SIZE=64`, `REDIS_POOL_WORKER_SIZE=64`. Request deduplication tắt mặc định, `/` trong excluded_paths. |
| **Warm cache** | Trước khi benchmark: gọi GET `/` vài lần để Redis và L1 có dữ liệu. |

**Cấu hình đã áp dụng:** `worker_num`/`reactor_num` mặc định `max(16, CPU*6)`, pool Redis/DB 64/worker, HTTP/2 tắt, `buffer_output_size` 8MB, `max_connection` 50000.

**Lệnh wrk gợi ý:**

```bash
# Bước 1: Warm cache (bắt buộc – Redis + L1 có dữ liệu)
curl -s http://localhost:888/ > /dev/null
curl -s http://localhost:888/ > /dev/null

# Bước 2: Kiểm tra fast path (X-Cache: HIT hoặc HIT-L1)
curl -sI http://localhost:888/ | grep -i x-cache

# Bước 3: Benchmark (tăng -c nếu ổn)
wrk -t12 -c200 -d30s --timeout 5s http://localhost:888/
wrk -t12 -c400 -d30s --timeout 5s http://localhost:888/
```

**Điều kiện:** `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, đã warm cache. Trên `.env`: `SWOOLE_WORKER_NUM=32` (hoặc 48 tùy CPU), `SWOOLE_REACTOR_NUM=32`, `DB_POOL_WORKER_SIZE=64`, `REDIS_POOL_WORKER_SIZE=64`.

---

## 10. Xử lý Timeout > 1000ms (hoặc hàng nghìn timeout khi chạy wrk)

**Triệu chứng:** wrk báo rất nhiều timeout, hoặc latency trung bình/max > 1000ms.

| Cách xử lý | Mô tả |
|------------|--------|
| **1. Tăng timeout của wrk** | wrk mặc định timeout ~1–2s. Request chậm bị đóng và đếm là timeout. Chạy với `--timeout 10s` hoặc `--timeout 30s` để giảm “timeout ảo”: `wrk -t12 -c200 -d30s --timeout 10s http://localhost:888/` |
| **2. Tăng Swoole max_wait_time** | Swoole có thể kill request nếu xử lý quá lâu. Trong `.env` đặt `SWOOLE_MAX_WAIT_TIME=10` (hoặc 30) giây. Cấu hình trong `config/server.php` → `max_wait_time`. |
| **3. Giảm latency (warm cache, giảm tải)** | Đảm bảo đã warm cache GET `/` (curl vài lần). Nếu vẫn timeout: giảm số kết nối đồng thời của wrk (`-c 100` thay vì 400) để mỗi worker ít request hơn, giảm xếp hàng. |
| **4. Tăng pool / worker** | Nếu request chờ connection Redis/DB → latency tăng. Tăng `REDIS_POOL_WORKER_SIZE`, `DB_POOL_WORKER_SIZE` (vd: 64) và `SWOOLE_WORKER_NUM` (vd: 32) trong `.env`. |
| **5. Kiểm tra Redis/DB** | Redis hoặc DB chậm sẽ kéo latency lên. Kiểm tra `redis-cli --latency`, và xem log Swoole/Redis có lỗi hay cảnh báo không. |

**Ví dụ wrk khi bị nhiều timeout:**

```bash
# Timeout wrk 10s, ít connection trước
wrk -t8 -c100 -d30s --timeout 10s http://localhost:888/

# Nếu ổn mới tăng -c
wrk -t8 -c200 -d30s --timeout 10s http://localhost:888/
```

---

## 11. Tối ưu thêm khi wrk -c1000: timeout 9000+, non-2xx 3000+, latency ~950ms

**Triệu chứng:** Chạy `wrk -t12 -c1000 -d60s` → ~678 req/s, timeout 9233, non-2xx 3632, latency avg 950ms, max 2s.

| Nguyên nhân | Cách xử lý |
|-------------|------------|
| **-c 1000 quá cao so với worker** | 1000 connections với 32 workers ≈ 31 request/worker đồng thời. Request xếp hàng chờ worker → latency tăng, dễ timeout. **Không dùng -c 1000 khi benchmark GET /.** Bắt đầu với `-c 200` hoặc `-c 400`, dùng `--timeout 10s`. Chỉ tăng -c khi latency ổn (< 200ms avg). |
| **wrk timeout mặc định ~1–2s** | Request chậm (> 1s) bị wrk đóng → đếm là timeout. Luôn dùng `--timeout 10s` (hoặc 30s) khi chạy wrk. |
| **Non-2xx (503 Service Unavailable)** | Có thể do pool Redis/DB hết connection hoặc circuit breaker mở. Kiểm tra log Swoole/Redis. Tăng `REDIS_POOL_WORKER_SIZE=64`, `DB_POOL_WORKER_SIZE=64`. Nếu cần, tạm tắt circuit breaker khi benchmark: `REDIS_CIRCUIT_BREAKER_ENABLED=false` (chỉ để test). |
| **Fast path chưa tối ưu** | Kernel fast path đã có **L1 per-worker**: request đầu trong worker lấy Redis, request sau trong cùng worker dùng L1 (không gọi Redis). Session cookie name cache trong static. Đảm bảo **warm cache** trước: `curl -s http://localhost:888/` vài lần, sau đó `curl -sI http://localhost:888/ | grep -i x-cache` phải thấy `X-Cache: HIT` hoặc `HIT-L1`. |

**Quy trình benchmark đề xuất (hướng 2000 req/s):**

```bash
# 1. Warm cache (bắt buộc)
for i in 1 2 3 4 5; do curl -s http://localhost:888/ > /dev/null; done
curl -sI http://localhost:888/ | grep -i x-cache   # Phải thấy HIT hoặc HIT-L1

# 2. Bắt đầu với ít connection + timeout cao (tránh timeout ảo)
wrk -t12 -c200 -d60s --timeout 10s http://localhost:888/

# 3. Nếu latency avg < 200ms, không timeout, không non-2xx → tăng -c
wrk -t12 -c400 -d60s --timeout 10s http://localhost:888/
wrk -t12 -c600 -d60s --timeout 10s http://localhost:888/
```

**Checklist .env:** `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `SWOOLE_WORKER_NUM=32`, `SWOOLE_REACTOR_NUM=32`, `DB_POOL_WORKER_SIZE=64`, `REDIS_POOL_WORKER_SIZE=64`, `SWOOLE_MAX_WAIT_TIME=10`.

---

## 12. Đề xuất cải thiện (khi đạt ~1000 req/s nhưng còn ~10k timeout, ~4k non-2xx)

**Triệu chứng:** `wrk -t12 -c1000 -d60s http://localhost:888/` → ~1059 req/s, timeout 9987, non-2xx 3938, latency avg 714ms, max 2s.

| # | Đề xuất | Hành động cụ thể |
|---|---------|-------------------|
| 1 | **Luôn dùng `--timeout 10s`** | wrk mặc định timeout ~1–2s. Request > 1s bị đóng → đếm là timeout. Chạy: `wrk -t12 -c400 -d60s --timeout 10s http://localhost:888/` để giảm “timeout ảo” và đo đúng throughput. |
| 2 | **Giảm `-c` xuống 300–500** | 1000 connections với 32 workers ≈ 31 request/worker đồng thời → xếp hàng, latency tăng. Thử `-c 300` hoặc `-c 500`: thường latency giảm mạnh, số timeout giảm, RPS có thể **tăng** (vì ít request bị hủy). |
| 3 | **Warm cache trước khi wrk** | Gọi GET `/` vài lần (curl hoặc wrk 5s với -c 4) để Redis + L1 có dữ liệu. Kiểm tra: `curl -sI http://localhost:888/ \| grep -i x-cache` phải thấy `X-Cache: HIT` hoặc `HIT-L1`. |
| 4 | **Tăng worker nếu CPU còn trống** | Nếu máy có nhiều core: `SWOOLE_WORKER_NUM=48`, `SWOOLE_REACTOR_NUM=48`. Công thức tham khảo: `min(số_core * 4, 64)`. Khởi động lại Swoole sau khi đổi .env. |
| 5 | **Kiểm tra non-2xx (503, 429, 500)** | Xem log Swoole/application: 503 thường do pool hết connection hoặc circuit breaker mở. Đảm bảo `REDIS_POOL_WORKER_SIZE=64`, `DB_POOL_WORKER_SIZE=64`. Benchmark có thể tạm: `REDIS_CIRCUIT_BREAKER_ENABLED=false`, `DB_CIRCUIT_BREAKER_ENABLED=false` (chỉ test). |
| 6 | **Giữ pool đủ lớn** | Mỗi worker cần đủ connection Redis/DB. Cấu hình: `DB_POOL_WORKER_SIZE=64`, `REDIS_POOL_WORKER_SIZE=64`. Tổng connection ≈ worker_num × pool_size; không nhỏ hơn số request đồng thời ước tính. |
| 7 | **Benchmark từ thấp lên cao** | Thứ tự gợi ý: (1) warm cache, (2) `wrk -t12 -c200 -d60s --timeout 10s`, (3) nếu latency avg < 150ms và timeout ≈ 0 → `-c 400`, rồi `-c 600`. Chỉ tăng `-c` khi latency ổn định. |

**Lệnh benchmark đề xuất (hướng 2000 req/s, ít timeout):**

```bash
# Warm cache
curl -s http://localhost:888/ > /dev/null; curl -s http://localhost:888/ > /dev/null
curl -sI http://localhost:888/ | grep -i x-cache

# Benchmark với timeout 10s và -c vừa phải (tránh xếp hàng)
wrk -t12 -c300 -d60s --timeout 10s http://localhost:888/
wrk -t12 -c500 -d60s --timeout 10s http://localhost:888/
```

**Kỳ vọng:** Giảm `-c` + `--timeout 10s` + warm cache → latency giảm, timeout gần 0, non-2xx giảm; RPS có thể tăng (do ít request bị hủy). Sau đó tăng dần `-c` hoặc `SWOOLE_WORKER_NUM` để tiến tới 2000 req/s.
