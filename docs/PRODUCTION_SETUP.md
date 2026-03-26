# Chuyển dự án sang Production

## 1. Cấu hình môi trường (.env)

```bash
# Tạo .env từ bản production (giữ lại .env hiện tại nếu đã có, chỉ sửa các biến sau)
cp .env .env.backup
# Hoặc tạo mới từ mẫu production:
cp .env.production.example .env
```

**Chỉnh trong `.env`:**

| Biến | Production |
|------|------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain.com` (đúng domain thật) |
| `APP_KEY` | Phải có; tạo bằng: `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` |
| `LOG_LEVEL` | `warning` (hoặc `error`) |
| `SESSION_SECURE_COOKIE` | `true` nếu dùng HTTPS |
| `CMS_AUTO_SYNC_BLOCKS` | `false` (mặc định khi APP_ENV=production) |
| `SWOOLE_DAEMONIZE` | `true` nếu chạy Swoole nền |

Cập nhật thêm: `DB_*`, `REDIS_*`, `MAIL_*`, `RPC_SECRET_TOKEN` theo server production.

## 2. Build cache module blocks (CMS)

Trên production, module blocks dùng file cache thay vì scan mỗi request:

```bash
# Tạo thư mục cache nếu chưa có
mkdir -p bootstrap/cache
# Chạy một request hoặc lệnh để build cache (ví dụ truy cập trang chủ hoặc chạy sync)
php cli cms:sync-blocks
```

Sau lần đầu chạy với `APP_ENV=production`, file `bootstrap/cache/module-blocks.php` sẽ được tạo.

## 3. Composer (production)

```bash
composer install --no-dev --optimize-autoloader
```

## 4. Chạy Swoole

```bash
# Chạy nền (sau khi SWOOLE_DAEMONIZE=true)
php cli serve:start
# Hoặc theo cách deploy của bạn (systemd, supervisor, Docker, …)
```

## 5. Lệnh ACL & cache lệnh (CLI)

Nếu chạy `php cli acl:optimize warm` báo **"There are no commands defined in the acl namespace"**:

```bash
php cli command:clear
php cli acl:optimize warm
```

`command:clear` xóa cache danh sách lệnh; lần chạy tiếp theo sẽ discover lại (gồm lệnh User module).

## 6. Docker (nếu chạy bằng Docker)

- Tạo `.env` từ `.env.example` hoặc `.env.production.example`, chỉnh `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`.
- **Chạy lần đầu có seed:** `RUN_SEEDERS=true docker compose up -d` (hoặc set `RUN_SEEDERS=true` trong `.env` rồi `docker compose up -d`). Các lần sau bỏ `RUN_SEEDERS` hoặc để `false` để không chạy seed mỗi lần start.
- **phpMyAdmin** (profile tools): `docker compose --profile tools up -d` nếu cần.
- Build lại image: `docker compose build --no-cache` (sau khi đổi code hoặc Dockerfile).

**Cảnh báo biến:** Nếu thấy `The "NGINX_HOST_PORT" variable is not set` khi chạy `docker-compose exec`, thêm vào `.env` (hoặc copy từ `.env.example`):

```
NGINX_HOST_PORT=888
VITE_HOST_PORT=5173
```

## 7. Kiểm tra nhanh

- Mở `APP_URL` trên trình duyệt: trang chủ load bình thường.
- Đăng nhập: session/cookie hoạt động (HTTPS thì `SESSION_SECURE_COOKIE=true`).
- Log không còn stack trace ra ngoài (APP_DEBUG=false).
- Log level: chỉ warning/error (LOG_LEVEL=warning).

## Tóm tắt biến production quan trọng

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=base64:...
LOG_LEVEL=warning
CMS_AUTO_SYNC_BLOCKS=false
SESSION_SECURE_COOKIE=true
SWOOLE_DAEMONIZE=true
```

Sau khi sửa `.env`, cần **restart Swoole** (hoặc PHP-FPM) để áp dụng.
