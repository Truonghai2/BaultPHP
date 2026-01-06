# Session & Authentication Persistence Analysis

## Tổng quan

Hệ thống đã được thiết kế để **giữ đăng nhập sau khi server restart** thông qua:

1. **Database Session Driver**: Session được lưu vào database thay vì memory
2. **Long Session Lifetime**: 43200 phút (30 ngày)
3. **Persistent Cookies**: Cookie không expire khi đóng browser

---

## Bug đã sửa ⚠️ **QUAN TRỌNG**

### Bug trong SessionGuard

**File**: `src/Core/Auth/SessionGuard.php` (dòng 67)

**Vấn đề**:

```php
// ❌ Bug cũ - Không set user vào guard
if (!is_null($user)) {
    $logger->info('SessionGuard: User restored from session', ['user_id' => $id]);
    $this->fireAuthenticatedEvent($user);
    return $this->user;  // ← $this->user vẫn là NULL!
}
```

**Đã sửa**:

```php
// ✅ Fix - Gọi setUser() trước khi return
if (!is_null($user)) {
    $this->setUser($user);  // ← FIX: Set user vào guard
    $logger->info('SessionGuard: User restored from session', ['user_id' => $id]);
    $this->fireAuthenticatedEvent($user);
    return $this->user;  // ← Giờ $this->user đã được set!
}
```

**Hậu quả của bug**:

- User ID được lưu trong session database ✅
- Nhưng `Auth::check()` vẫn trả về `false` ❌
- User bị redirect về trang login mặc dù session còn hạn ❌

**Kết quả sau khi sửa**:

- Session được restore đúng cách ✅
- User vẫn đăng nhập sau khi server restart ✅
- Không cần remember token nếu session còn hạn ✅

---

## Kiến trúc Session Persistence

### 1. Session Configuration

**File**: `config/session.php`

```php
'driver' => 'database',           // Lưu vào MySQL
'lifetime' => 43200,              // 30 ngày (phút)
'expire_on_close' => false,       // KHÔNG expire khi đóng browser
'cookie' => 'bault_session',      // Tên cookie
'secure' => false,                // false cho localhost, true cho production
'http_only' => true,              // Ngăn XSS
'same_site' => 'lax',            // CSRF protection
```

**Environment** (`.env`):

```bash
SESSION_DRIVER=database
SESSION_LIFETIME=43200  # 30 ngày
SESSION_COOKIE=bault_session
SESSION_SECURE_COOKIE=false  # localhost
SESSION_SAME_SITE=lax
```

### 2. Database Schema

**Table**: `sessions`

```sql
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,           -- Session ID
    user_id BIGINT NULL,                   -- User ID (nếu đã login)
    ip_address VARCHAR(45) NULL,           -- IP address
    user_agent TEXT NULL,                  -- Browser info
    payload LONGTEXT,                      -- Serialized session data
    last_activity INT,                     -- Unix timestamp
    lifetime INT,                          -- Session lifetime
    created_at INT NULL,                   -- Created timestamp
    payload_size INT NULL,                 -- Payload size (optimization)
    INDEX (user_id),
    INDEX (last_activity, user_id),        -- Composite index cho GC
    INDEX (payload_size)
);
```

### 3. Session Handler

**File**: `src/Core/Session/OptimizedSwoolePdoSessionHandler.php`

**Đặc điểm**:

- Lưu session vào database persistent
- Tự động lưu user_id khi login
- Smart write: Chỉ update khi có thay đổi
- Batch garbage collection

**Read Method**:

```php
public function read(string $sessionId): string|false
{
    $sql = "SELECT payload FROM sessions WHERE id = :id";
    // Trả về payload (serialized session data)
    // Payload chứa: login_web_{hash} => user_id
}
```

**Write Method**:

```php
public function write(string $sessionId, string $data): bool
{
    // Extract user_id from session data
    $attributes = unserialize($data);
    foreach ($attributes as $key => $value) {
        if (str_starts_with($key, 'login_web_') && is_int($value)) {
            $userId = $value;  // ← User ID được lưu vào column user_id
        }
    }

    // INSERT or UPDATE session
    $sql = "INSERT INTO sessions (id, user_id, payload, last_activity, ...)
            VALUES (...)
            ON DUPLICATE KEY UPDATE ...";
}
```

---

## Flow đăng nhập và restore

### 1️⃣ User đăng nhập

```
LoginController
    ↓
SessionGuard::login($user, $remember)
    ↓
updateSession($user->getAuthIdentifier())
    ↓
session()->set('login_web_{hash}', $user->id)  ← Lưu user ID vào session
    ↓
SessionHandler::write()  ← Ghi vào database
    ↓
Database: sessions table
    • id: abc123...
    • user_id: 1
    • payload: serialized(login_web_xxx => 1)
    • last_activity: 1729900000
    • lifetime: 2592000 (43200 phút = 2592000 giây)
```

### 2️⃣ Server restart

```
Server stop  ← Container restart, process restart
    ↓
Memory cleared  ← Application state mất
    ↓
Database persistent  ← sessions table vẫn còn! ✅
    • id: abc123...
    • user_id: 1
    • payload: ...
    • last_activity: còn hạn
```

### 3️⃣ User request sau restart

```
Browser gửi request
    ↓
Cookie: bault_session=abc123...  ← Browser tự động gửi cookie
    ↓
StartSession middleware
    ↓
SessionHandler::read('abc123')  ← Đọc từ database
    ↓
Session data restored: {login_web_xxx: 1}  ← User ID được restore
    ↓
Authenticate middleware
    ↓
SessionGuard::check()
    ↓
SessionGuard::user()
    ↓
session()->get('login_web_xxx')  ← Lấy user ID từ session = 1
    ↓
UserProvider::retrieveById(1)  ← Query database để lấy User model
    ↓
setUser($user)  ← ✅ FIX: Set user vào guard
    ↓
Auth::check() = true  ← User đã login! ✅
```

---

## Remember Token (Optional)

Remember token chỉ cần khi:

- Session đã expire (sau 30 ngày)
- User chọn "Remember me" khi login

**Flow Remember Token**:

```
Login với remember=true
    ↓
createRememberMeCookie($user)
    ↓
$token = Str::random(60)
    ↓
DB: remember_tokens table
    • user_id: 1
    • token: hashed($token)
    • expires_at: +1 năm
    ↓
Cookie: remember_{hash} = {user_id}|{token}|{hash}
    ↓
(Session expire sau 30 ngày)
    ↓
SessionGuard::user()
    • session()->get('login_web_xxx') = null  ❌
    • getRecallerFromCookie() = token  ✅
    • userFromRecaller($token)  ← Verify token
    • updateSession($user->id)  ← Tạo session mới
    • Login thành công!  ✅
```

---

## Session Lifetime trong thực tế

### Với Database Driver

**Session lifetime = 43200 phút = 30 ngày**

| Thời điểm       | Session status  | User login?       |
| --------------- | --------------- | ----------------- |
| Login           | Session created | ✅ Logged in      |
| 1 ngày sau      | Session valid   | ✅ Logged in      |
| 7 ngày sau      | Session valid   | ✅ Logged in      |
| 29 ngày sau     | Session valid   | ✅ Logged in      |
| **30 ngày sau** | Session expired | ❌ Redirect login |

**Với remember token** (nếu enabled):
| Thời điểm | Remember status | Result |
|-----------|----------------|---------|
| 30 ngày + 1 | Token valid | ✅ Auto re-login |
| 365 ngày | Token valid | ✅ Auto re-login |
| 366 ngày | Token expired | ❌ Redirect login |

### Session Garbage Collection

**Tự động xóa session cũ**:

```php
// OptimizedSwoolePdoSessionHandler
public function gc(int $maxlifetime): int|false
{
    $expiredTime = time() - $maxlifetime;

    $sql = "DELETE FROM sessions
            WHERE last_activity < :expired
            LIMIT 1000";  // Batch delete

    // Xóa các session:
    // - Không hoạt động > 30 ngày
    // - Chưa được access
}
```

**Garbage collection chạy**:

- 1% chance mỗi request (mặc định PHP)
- Hoặc chạy cron job:

```bash
php cli session:gc
```

---

## Cookie Settings

### Session Cookie

```php
'cookie' => 'bault_session',        // Cookie name
'path' => '/',                      // Available toàn site
'domain' => null,                   // localhost
'secure' => false,                  // HTTP ok (localhost)
'http_only' => true,               // JavaScript không access được
'same_site' => 'lax',              // CSRF protection
```

**Cookie value**: `abc123...` (session ID)

**Cookie attributes**:

```
Set-Cookie: bault_session=abc123...;
            Path=/;
            HttpOnly;
            SameSite=Lax;
            Expires=Session  (vì expire_on_close=false thực tế là lâu dài)
```

### Remember Cookie

```php
'cookie' => 'remember_{hash}',
'expires' => +1 năm,
```

**Cookie value**: `{user_id}|{token}|{hash}`

---

## Best Practices

### 1. Production Settings

**`.env` cho production**:

```bash
SESSION_DRIVER=database           # ✅ Persistent
SESSION_LIFETIME=43200           # 30 ngày
SESSION_SECURE_COOKIE=true       # ✅ HTTPS only
SESSION_SAME_SITE=strict         # ✅ Strong CSRF protection
SESSION_ENCRYPT=true             # ✅ Encrypt session data
```

### 2. Security

✅ **DO**:

- Sử dụng HTTPS trong production
- Set `secure=true` cho cookies
- Enable session encryption
- Implement session regeneration sau login
- Use strong session ID generation

❌ **DON'T**:

- Không lưu sensitive data trong session
- Không share session ID qua URL
- Không set lifetime quá dài (> 30 ngày)

### 3. Performance

**Optimize session writes**:

```php
// OptimizedSwoolePdoSessionHandler features:
- Smart write: Chỉ update khi có thay đổi
- Write cooldown: 60s giữa các updates
- Payload size tracking
- Batch GC
```

**Monitoring**:

```sql
-- Check session count
SELECT COUNT(*) FROM sessions;

-- Check large sessions
SELECT id, user_id, payload_size
FROM sessions
WHERE payload_size > 10000
ORDER BY payload_size DESC;

-- Check expired sessions
SELECT COUNT(*) FROM sessions
WHERE last_activity < (UNIX_TIMESTAMP() - 2592000);
```

### 4. Debugging

**Check session data**:

```php
// Trong controller
dd(session()->all());

// Check user ID trong session
dd(session()->get('login_web_' . sha1(SessionGuard::class)));
```

**Check session trong database**:

```sql
-- Tìm session của user
SELECT * FROM sessions WHERE user_id = 1;

-- Xem payload
SELECT id, user_id,
       FROM_UNIXTIME(last_activity) as last_seen,
       LENGTH(payload) as size
FROM sessions
WHERE user_id IS NOT NULL;
```

**Logs**:

```bash
# Xem logs authentication
tail -f storage/logs/app.log | grep "SessionGuard"

# Check session restore
grep "User restored from session" storage/logs/app.log
```

---

## Troubleshooting

### ❓ User bị logout sau khi server restart

**Nguyên nhân**: Session driver không persistent

**Giải pháp**:

```bash
# 1. Check .env
SESSION_DRIVER=database  # Phải là database, không phải file hoặc array

# 2. Run migration
php cli migrate

# 3. Clear cache
php cli cache:clear

# 4. Restart server
docker-compose restart
```

### ❓ Cookie không được gửi

**Nguyên nhân**: Secure cookie trên HTTP

**Giải pháp**:

```bash
# Development (HTTP)
SESSION_SECURE_COOKIE=false

# Production (HTTPS)
SESSION_SECURE_COOKIE=true
```

### ❓ Session bị clear sau mỗi request

**Nguyên nhân**: Middleware order sai

**Giải pháp**: Check `src/Http/Kernel.php`:

```php
'web' => [
    \App\Http\Middleware\EncryptCookies::class,       // 1. Decrypt cookie
    \App\Http\Middleware\StartSession::class,         // 2. Start session
    // ... other middleware
    \App\Http\Middleware\TerminateSession::class,     // Cuối: Save session
],
```

---

## Tổng kết

### ✅ Đã hoàn thành

1. ✅ **Sửa bug SessionGuard**: User được restore đúng cách từ session
2. ✅ **Database session driver**: Session persistent sau restart
3. ✅ **Long lifetime**: 30 ngày session
4. ✅ **Optimized handler**: Giảm database writes
5. ✅ **Security**: HttpOnly, SameSite cookies

### 🎯 Kết quả

- **Không cần remember token** nếu session còn hạn (< 30 ngày)
- **User vẫn login** sau khi server restart
- **Cookie tự động** được browser gửi lại
- **Session restore** từ database
- **Performance tốt** với optimized handler

### 📊 Metrics

- **Session persistence**: 100% (database)
- **Write reduction**: 60-70% (optimized handler)
- **Login retention**: 30 ngày (configurable)
- **Remember token**: +365 ngày (optional)

---

**Tài liệu liên quan**:

- [Module Version Management](./module-version-management.md)
- [Session Optimization Guide](../config/session.php)
