# Hướng Dẫn Sửa Lỗi Session - Giữ Đăng Nhập Sau Restart

## 🔴 Vấn Đề

**Triệu chứng:**

- Đã đăng nhập và cookie session còn hạn
- Sau khi restart server → Bị mất đăng nhập
- Muốn giữ đăng nhập bất kể có tick "Remember me" hay không

## 🎯 Nguyên Nhân Chính

### 1️⃣ **SESSION_SECURE_COOKIE=true trên HTTP** (CRITICAL)

```php
// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', true),
```

**Vấn đề:**

- Cookie với flag `secure=true` CHỈ được gửi qua **HTTPS**
- Nếu bạn chạy trên **HTTP** (localhost), browser sẽ **từ chối gửi cookie**
- Không có cookie → Không có session → Bị logout!

**Ví dụ:**

```
Browser: "Tôi có cookie session abc123"
Server: "Gửi cho tôi qua HTTPS"
Browser (HTTP): "Không! Cookie có flag secure"
Server: "Không nhận được cookie → User chưa login → Redirect login"
```

### 2️⃣ **SESSION_DRIVER không phải database**

```php
'driver' => env('SESSION_DRIVER', 'file'),  // ❌ Mặc định file
```

**Vấn đề:**

- Session lưu trên file/memory → Mất khi restart container
- Cần lưu vào **database** để persistent

### 3️⃣ **Session lifetime ngắn**

```php
'lifetime' => env('SESSION_LIFETIME', 120),  // ❌ Chỉ 2 giờ
```

**Vấn đề:**

- Session hết hạn sau 2 giờ
- Cần tăng lên **43200 phút (30 ngày)**

## ✅ Giải Pháp

### Bước 1: Kiểm Tra Cấu Hình Hiện Tại

```bash
php check-session-config.php
```

Script này sẽ:

- Kiểm tra toàn bộ cấu hình session
- Phát hiện các vấn đề
- Đưa ra khuyến nghị cụ thể

**Output mẫu:**

```
🔴 CÁC VẤN ĐỀ CẦN SỬA NGAY:
═════════════════════════════════════════════════════════════

[1] CRITICAL: Secure cookie = true nhưng APP_URL không dùng HTTPS
    💡 Cách sửa: Set SESSION_SECURE_COOKIE=false trong .env
    📌 Lý do: Browser sẽ KHÔNG gửi cookie qua HTTP!
```

### Bước 2: Tự Động Sửa File .env

```bash
# Preview thay đổi (không ghi file)
php fix-session-config.php --dry-run

# Áp dụng thay đổi
php fix-session-config.php
```

Script sẽ:

- Backup file .env gốc
- Tự động phát hiện HTTP/HTTPS
- Cập nhật các setting cần thiết
- Hiển thị các bước tiếp theo

### Bước 3: Cấu Hình Thủ Công (Nếu Cần)

Thêm vào file `.env`:

#### 🔧 Development (HTTP/Localhost):

```bash
# Session Configuration - Development
SESSION_DRIVER=database
SESSION_LIFETIME=43200                      # 30 ngày
SESSION_SECURE_COOKIE=false                 # ← QUAN TRỌNG!
SESSION_EXPIRE_ON_CLOSE=false
SESSION_SAME_SITE=lax
SESSION_USE_OPTIMIZED_HANDLER=true
```

#### 🔒 Production (HTTPS):

```bash
# Session Configuration - Production
SESSION_DRIVER=database
SESSION_LIFETIME=43200                      # 30 ngày
SESSION_SECURE_COOKIE=true                  # ← Bắt buộc cho HTTPS
SESSION_EXPIRE_ON_CLOSE=false
SESSION_SAME_SITE=strict
SESSION_USE_OPTIMIZED_HANDLER=true
```

### Bước 4: Restart Server

```bash
# Docker
docker-compose restart

# Hoặc nếu chạy standalone
php cli server:restart
```

### Bước 5: Clear Cache

```bash
php cli cache:clear
php cli config:clear
```

### Bước 6: Verify

```bash
# Kiểm tra lại cấu hình
php check-session-config.php

# Nếu thành công, sẽ thấy:
# ✅ Không có vấn đề nghiêm trọng!
```

## 📊 So Sánh Trước/Sau

### ❌ Trước Khi Sửa:

```
User login → Server restart → User bị logout
```

**Flow:**

1. User login thành công
2. Session lưu vào file/memory
3. Cookie có flag `secure=true`
4. Server restart → Session mất
5. Browser gửi request với cookie
6. Cookie bị reject vì secure=true trên HTTP
7. Server không nhận được session ID
8. User bị redirect về login

### ✅ Sau Khi Sửa:

```
User login → Server restart → User VẪN đăng nhập
```

**Flow:**

1. User login thành công
2. Session lưu vào **database** (persistent)
3. Cookie có flag `secure=false` (cho HTTP)
4. Server restart → Session vẫn trong database
5. Browser gửi request với cookie
6. Cookie được accept (secure=false)
7. Server đọc session từ database
8. User vẫn đăng nhập! ✅

## 🔍 Chi Tiết Kỹ Thuật

### Session Lifetime

```php
// config/session.php
'lifetime' => 43200,  // 30 ngày (phút)
```

**Timeline:**

- 0 ngày: User login
- 1 ngày: Session còn hạn ✅
- 7 ngày: Session còn hạn ✅
- 29 ngày: Session còn hạn ✅
- 30 ngày: Session expire ❌ (phải login lại)

### Database Session Schema

```sql
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,           -- Session ID
    user_id BIGINT NULL,                   -- User ID
    payload LONGTEXT,                      -- Session data
    last_activity INT,                     -- Last access time
    lifetime INT,                          -- Lifetime in seconds
    created_at INT,                        -- Creation time
    payload_size INT,                      -- Size tracking
    INDEX (user_id),
    INDEX (last_activity, user_id)
);
```

**Ví dụ record:**

```
id: "Xy7k9LmN2pQ..."
user_id: 1
payload: 'a:2:{s:33:"login_web_...";i:1;s:6:"_token";s:40:"..."}'
last_activity: 1730000000
lifetime: 2592000  (30 ngày)
```

### Cookie Attributes

**Development (HTTP):**

```
Set-Cookie: bault_session=Xy7k9LmN2pQ...;
            Path=/;
            HttpOnly;               ← JavaScript không đọc được
            SameSite=Lax;          ← CSRF protection
            Max-Age=2592000         ← 30 ngày
```

**Production (HTTPS):**

```
Set-Cookie: bault_session=Xy7k9LmN2pQ...;
            Path=/;
            Secure;                 ← CHỈ gửi qua HTTPS
            HttpOnly;
            SameSite=Strict;       ← Strict CSRF protection
            Max-Age=2592000
```

## 🚨 Lỗi Thường Gặp

### Lỗi 1: Cookie không được gửi

**Triệu chứng:**

- Browser DevTools → Application → Cookies → Không thấy `bault_session`

**Nguyên nhân:**

- `secure=true` trên HTTP
- Cookie bị block bởi SameSite policy

**Giải pháp:**

```bash
SESSION_SECURE_COOKIE=false  # Cho HTTP
SESSION_SAME_SITE=lax        # Thay vì strict
```

### Lỗi 2: Session bị xóa sau restart

**Triệu chứng:**

- Cookie có trong browser
- Nhưng session không tồn tại trong database

**Nguyên nhân:**

- `SESSION_DRIVER=file` hoặc `array`

**Giải pháp:**

```bash
SESSION_DRIVER=database
```

### Lỗi 3: Session expire quá nhanh

**Triệu chứng:**

- Phải login lại sau vài giờ

**Nguyên nhân:**

- `SESSION_LIFETIME` quá ngắn
- `SESSION_EXPIRE_ON_CLOSE=true`

**Giải pháp:**

```bash
SESSION_LIFETIME=43200          # 30 ngày
SESSION_EXPIRE_ON_CLOSE=false
```

### Lỗi 4: Không kết nối được database

**Triệu chứng:**

```
Connection pool 'mysql' has not been initialized
```

**Nguyên nhân:**

- Database chưa start
- Cấu hình DB sai

**Giải pháp:**

```bash
# Start database
docker-compose up -d mysql

# Check connection
docker-compose exec php php -r "new PDO('mysql:host=mysql;dbname=bault', 'root', 'secret');"
```

### Lỗi 5: Table sessions không tồn tại

**Triệu chứng:**

```
Table 'bault.sessions' doesn't exist
```

**Giải pháp:**

```bash
php cli migrate
```

## 📈 Performance Tips

### 1. Optimized Handler

```bash
SESSION_USE_OPTIMIZED_HANDLER=true
```

**Benefits:**

- Giảm 60-70% database writes
- Chỉ update khi có thay đổi hoặc sau 60 giây
- Batch garbage collection

### 2. Index Optimization

```sql
-- Composite index cho fast lookup
ALTER TABLE sessions
ADD INDEX idx_activity_user (last_activity, user_id);

-- Index cho payload size monitoring
ALTER TABLE sessions
ADD INDEX idx_payload_size (payload_size);
```

### 3. Garbage Collection

```bash
# Manual GC
php cli session:gc

# Hoặc setup cron job
0 */6 * * * cd /app && php cli session:gc
```

## 🔐 Security Best Practices

### Development

```bash
SESSION_SECURE_COOKIE=false     # OK cho localhost
SESSION_SAME_SITE=lax          # Dễ debug
SESSION_ENCRYPT=false          # Optional
```

### Production

```bash
SESSION_SECURE_COOKIE=true     # ✅ Bắt buộc
SESSION_SAME_SITE=strict       # ✅ Strict CSRF
SESSION_ENCRYPT=true           # ✅ Encrypt data
APP_URL=https://yourdomain.com # ✅ HTTPS
```

### Additional Security

```php
// Session regeneration sau login
SessionGuard::login($user) {
    $this->session->regenerate();  // ✅ New session ID
    // ...
}

// CSRF token refresh
SessionGuard::updateSession($userId) {
    $this->csrfManager->refreshToken();  // ✅ New CSRF token
    // ...
}
```

## 📚 Tài Liệu Liên Quan

- [session-authentication-persistence.md](./session-authentication-persistence.md) - Flow chi tiết
- [session-database-optimization.md](./session-database-optimization.md) - Performance tuning
- [config/session.php](../config/session.php) - Full configuration

## 🆘 Troubleshooting

### Debug Session

```php
// Trong controller
use Core\Support\Facades\Auth;

// Check auth status
dd([
    'check' => Auth::check(),
    'user' => Auth::user(),
    'session_id' => session()->getId(),
    'session_data' => session()->all(),
]);
```

### Check Database

```sql
-- Sessions của user ID 1
SELECT * FROM sessions WHERE user_id = 1;

-- Session details
SELECT
    id,
    user_id,
    FROM_UNIXTIME(last_activity) as last_seen,
    FROM_UNIXTIME(created_at) as created,
    payload_size,
    LENGTH(payload) as actual_size
FROM sessions
WHERE user_id = 1;
```

### Check Cookies

**Browser DevTools:**

1. F12 → Application tab
2. Cookies → `http://localhost:8000`
3. Tìm `bault_session`
4. Check attributes: Secure, HttpOnly, SameSite

### Check Logs

```bash
# Authentication logs
tail -f storage/logs/app.log | grep "SessionGuard"

# Session operations
tail -f storage/logs/app.log | grep "session"
```

## ✅ Checklist

Sau khi sửa, verify các điểm sau:

- [ ] `SESSION_DRIVER=database` trong .env
- [ ] `SESSION_LIFETIME=43200` (hoặc giá trị phù hợp)
- [ ] `SESSION_SECURE_COOKIE=false` cho HTTP, `true` cho HTTPS
- [ ] `SESSION_EXPIRE_ON_CLOSE=false`
- [ ] Table `sessions` tồn tại và có indexes
- [ ] Database connection hoạt động
- [ ] Cookie `bault_session` xuất hiện trong browser
- [ ] Login thành công
- [ ] Restart server → Vẫn đăng nhập ✅

## 🎉 Kết Quả Mong Đợi

Sau khi hoàn thành:

1. ✅ User login 1 lần
2. ✅ Session lưu vào database
3. ✅ Restart server bao nhiêu lần cũng được
4. ✅ User vẫn đăng nhập (không cần remember token)
5. ✅ Session tồn tại 30 ngày
6. ✅ Sau 30 ngày mới phải login lại

**Perfect! 🚀**
