# Security Fixes Implementation Guide

Hướng dẫn chi tiết để fix các lỗ hổng bảo mật đã phát hiện trong Security Audit.

---

## 🔥 IMMEDIATE FIXES (Critical)

### Fix #1: Enable Security Headers

**File**: `docker/nginx/security_headers.conf`

**Current**:
```nginx
# add_header X-Content-Type-Options "nosniff" always;
# add_header X-Frame-Options "SAMEORIGIN" always;
# ...
```

**Fix**:
```nginx
# Remove # to enable all headers
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), midi=(), camera=(), microphone=(), payment=()" always;

# Add HSTS for production
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

# Content Security Policy - Customize theo nhu cầu
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://jspm.dev; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self' https://jspm.dev; object-src 'none';" always;
```

**Test**:
```bash
# Restart nginx
docker-compose restart nginx

# Verify headers
curl -I https://your-domain.com

# Should see:
# X-Content-Type-Options: nosniff
# X-Frame-Options: SAMEORIGIN
# etc.
```

---

### Fix #2: File Upload Path Traversal

**File**: `Modules/Cms/Http/Controllers/MediaLibraryController.php`

**Current** (line 133):
```php
$folder = $data['folder'] ?? '/';
$path = 'uploads/media' . $folder . date('Y/m/');
$fullPath = base_path('public/' . $path);
```

**Fix**:
```php
$folder = $data['folder'] ?? '/';

// Sanitize folder path to prevent path traversal
$folder = str_replace(['..', '\\'], '', $folder); // Remove path traversal attempts
$folder = trim($folder, '/'); // Normalize slashes
$folder = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $folder); // Allow only safe chars

// Build path
$basePath = base_path('public/uploads/media/');
$relativePath = ($folder ? $folder . '/' : '') . date('Y/m/');
$fullPath = $basePath . $relativePath;

// Validate path is within base directory
$realPath = realpath(dirname($fullPath));
$realBase = realpath($basePath);

if (!$realPath || !str_starts_with($realPath, $realBase)) {
    return response()->json(['error' => 'Invalid upload path'], 400);
}

// Create directory if not exists
if (!is_dir($fullPath)) {
    mkdir($fullPath, 0755, true);
}
```

**Test**:
```php
// Try malicious payloads
$maliciousPayloads = [
    '../../../../config/',
    '..\\..\\..\\config\\',
    '/etc/passwd',
    'uploads/../../config',
];

foreach ($maliciousPayloads as $payload) {
    // Should all be blocked
    $response = $this->post('/media/upload', ['folder' => $payload]);
    $this->assertEquals(400, $response->getStatusCode());
}
```

---

### Fix #3: Add RawExpression Validation

**File**: `src/Core/ORM/RawExpression.php` (new file)

**Create**:
```php
<?php

namespace Core\ORM;

class RawExpression
{
    private string $value;
    
    // Whitelist của column names cho common operations
    private static array $allowedColumns = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'count', 'sum', 'avg', 'min', 'max',
    ];
    
    // Patterns nguy hiểm
    private static array $dangerousPatterns = [
        '/;\s*(DROP|DELETE|INSERT|UPDATE|ALTER|CREATE)/i',
        '/UNION.*SELECT/i',
        '/--/',
        '/#/',
        '/\/\*.*\*\//s',
        '/\bOR\b.*=.*/i',
        '/\bAND\b.*=.*/i',
    ];
    
    public function __construct(string $value)
    {
        $this->validateExpression($value);
        $this->value = $value;
    }
    
    private function validateExpression(string $value): void
    {
        // Check for SQL injection patterns
        foreach (self::$dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new \InvalidArgumentException(
                    'Potential SQL injection detected in RawExpression'
                );
            }
        }
        
        // Warn if using non-whitelisted columns (in development)
        if (config('app.debug')) {
            $hasWhitelisted = false;
            foreach (self::$allowedColumns as $col) {
                if (stripos($value, $col) !== false) {
                    $hasWhitelisted = true;
                    break;
                }
            }
            
            if (!$hasWhitelisted) {
                logger()->warning('RawExpression using non-whitelisted column', [
                    'expression' => $value,
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
                ]);
            }
        }
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function __toString(): string
    {
        return $this->value;
    }
    
    /**
     * Add custom allowed columns for specific tables
     */
    public static function addAllowedColumns(array $columns): void
    {
        self::$allowedColumns = array_merge(self::$allowedColumns, $columns);
    }
}
```

**Usage**:
```php
// In AppServiceProvider or config
RawExpression::addAllowedColumns([
    'users.email',
    'posts.title',
    'products.price',
]);

// In queries
DB::table('users')
    ->select(new RawExpression('COUNT(*) as total'))
    ->get();

// This will throw exception
try {
    $malicious = new RawExpression("1=1; DROP TABLE users--");
} catch (\InvalidArgumentException $e) {
    // Caught: Potential SQL injection detected
}
```

---

## 🔧 HIGH PRIORITY FIXES

### Fix #4: Add Session Regeneration on Login

**File**: `src/Core/Auth/SessionGuard.php`

**Update** `login()` method (line 101):
```php
public function login(Authenticatable $user, bool $remember = false): void
{
    $startTime = microtime(true);
    $logger = $this->app->make(\Psr\Log\LoggerInterface::class);
    
    // Regenerate session ID to prevent session fixation
    $oldSessionId = $this->session->getId();
    $this->session->regenerate(true); // Delete old session
    $newSessionId = $this->session->getId();
    
    $logger->info('Session regenerated on login', [
        'old_session_id' => substr($oldSessionId, 0, 8) . '...',
        'new_session_id' => substr($newSessionId, 0, 8) . '...',
        'user_id' => $user->getAuthIdentifier(),
    ]);
    
    $this->updateSession($user->getAuthIdentifier());

    if ($remember) {
        $this->createRememberMeCookie($user);
    }

    $this->setUser($user);
    $this->dispatcher?->dispatch(new Login('session', $user, $remember));

    $duration = microtime(true) - $startTime;
    $logger->info('SessionGuard login process finished.', [
        'user_id' => $user->getAuthIdentifier(),
        'duration_ms' => $duration * 1000,
    ]);
}
```

**Also update** `logout()` method (line 123):
```php
public function logout(): void
{
    $user = $this->user();
    $recaller = $this->getRecallerFromCookie();

    if ($user) {
        if ($recaller && isset($recaller['selector'])) {
            $this->removeRememberToken($recaller['selector']);
        }

        $this->dispatcher?->dispatch(new Logout('session', $user));
    }

    $this->forgetRecallerCookie();
    
    // Regenerate session ID để prevent session fixation sau logout
    $this->session->regenerate(true);
    
    $this->user = null;
    $this->userResolved = false;
}
```

---

### Fix #5: Apply Rate Limiting Globally

**File**: `src/Http/Kernel.php`

**Update** middleware groups:
```php
protected array $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Core\Middleware\AddQueuedCookiesToResponse::class,
        \App\Http\Middleware\StartSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        
        // Add rate limiting for web routes
        \App\Http\Middleware\ThrottleRequests::class . ':100,1', // 100 per minute
    ],

    'api' => [
        // More generous limit for API
        \App\Http\Middleware\ThrottleRequests::class . ':180,1', // 180 per minute
        'throttle:api',
    ],
];
```

**Add strict limits for auth routes**:

**File**: `routes/web.php` or auth routes:
```php
// Login - 5 attempts per minute
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('auth.login');

// Register - 3 per hour to prevent spam
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:3,60')
    ->name('auth.register');

// Password reset request - 5 per hour
Route::post('/forgot-password', [PasswordController::class, 'forgot'])
    ->middleware('throttle:5,60');

// API endpoints - per user
Route::middleware(['auth:api', 'throttle:60,1'])->group(function() {
    // API routes
});
```

---

### Fix #6: Add Pepper to Argon2i Hasher

**File**: `src/Core/Hashing/Argon2iHasher.php`

**Update**:
```php
<?php

declare(strict_types=1);

namespace Core\Hashing;

use RuntimeException;

class Argon2iHasher implements HasherInterface
{
    public function __construct(protected array $options = [])
    {
    }

    public function make(string $value, array $options = []): string
    {
        // Add pepper before hashing
        $value = $this->applyPepper($value);
        
        $hash = password_hash($value, PASSWORD_ARGON2I, $this->options($options));

        if ($hash === false) {
            throw new RuntimeException('Argon2i hashing failed.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }
        
        // Add pepper before verification
        $value = $this->applyPepper($value);
        
        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, PASSWORD_ARGON2I, $this->options($options));
    }

    protected function options(array $options): array
    {
        return array_merge([
            'memory_cost' => $this->options['memory_cost'] ?? PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => $this->options['time_cost'] ?? PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => $this->options['threads'] ?? PASSWORD_ARGON2_DEFAULT_THREADS,
        ], $options);
    }
    
    /**
     * Apply pepper (server-side secret) to password before hashing.
     * This adds an extra layer of security even if the database is compromised.
     */
    protected function applyPepper(string $value): string
    {
        $pepper = config('hashing.pepper', config('app.key'));
        
        if (!$pepper) {
            throw new RuntimeException('Hashing pepper not configured. Set APP_KEY in .env');
        }
        
        // Use HMAC to mix pepper with password
        return hash_hmac('sha256', $value, $pepper) . $value;
    }
}
```

**Also add to BcryptHasher**:
```php
// src/Core/Hashing/BcryptHasher.php
public function make(string $value, array $options = []): string
{
    $value = $this->applyPepper($value);
    
    $hash = password_hash($value, PASSWORD_BCRYPT, [
        'cost' => $this->options($options)['rounds'],
    ]);

    if ($hash === false) {
        throw new RuntimeException('Bcrypt hashing failed.');
    }

    return $hash;
}

public function check(string $value, string $hashedValue, array $options = []): bool
{
    if (strlen($hashedValue) === 0) {
        return false;
    }
    
    $value = $this->applyPepper($value);
    
    return password_verify($value, $hashedValue);
}

protected function applyPepper(string $value): string
{
    $pepper = config('hashing.pepper', config('app.key'));
    
    if (!$pepper) {
        throw new RuntimeException('Hashing pepper not configured');
    }
    
    return hash_hmac('sha256', $value, $pepper) . $value;
}
```

---

### Fix #7: HTML Sanitization in Blocks

**Install HTML Purifier**:
```bash
composer require ezyang/htmlpurifier
```

**File**: `Modules/Cms/Domain/Blocks/TextBlock.php`

**Update**:
```php
use HTMLPurifier;
use HTMLPurifier_Config;

class TextBlock extends AbstractBlock
{
    private static ?HTMLPurifier $purifier = null;
    
    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        $content = $context['content'] ?? '';
        $format = $config['format'] ?? 'html';

        // Process content based on format
        $processedContent = match ($format) {
            'markdown' => $this->renderMarkdown($content),
            'plain' => $this->escapeHtml($content),
            'html' => $this->sanitizeHtml($content), // FIX: Sanitize HTML
            default => $this->sanitizeHtml($content),
        };

        return $this->renderView('cms::blocks.text', array_merge($config, [
            'title' => $context['title'] ?? '',
            'content' => $processedContent,
        ]));
    }

    protected function sanitizeHtml(string $html): string
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            
            // Customize allowed tags and attributes
            $config->set('HTML.Allowed', implode(',', [
                'p', 'br', 'strong', 'em', 'u', 's', 'a[href|title|target]',
                'img[src|alt|width|height]', 'ul', 'ol', 'li',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'blockquote', 'code', 'pre',
                'table', 'thead', 'tbody', 'tr', 'th', 'td',
                'div[class]', 'span[class]',
            ]));
            
            // Allow target="_blank" for links
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            
            // Set cache directory
            $config->set('Cache.SerializerPath', storage_path('cache/htmlpurifier'));
            
            self::$purifier = new HTMLPurifier($config);
        }
        
        return self::$purifier->purify($html);
    }
    
    protected function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
```

---

### Fix #8: Rotate Remember Tokens

**File**: `src/Core/Auth/SessionGuard.php`

**Update** remember token methods:
```php
protected function createRememberMeCookie(Authenticatable $user): void
{
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);
    
    // Set expiration (30 days default)
    $lifetime = config('auth.remember_token_lifetime', 30 * 24 * 60); // minutes
    $expiresAt = time() + ($lifetime * 60);

    // Save to database with expiration
    $this->saveRememberToken(
        $user->getAuthIdentifier(),
        $selector,
        $validatorHash,
        $expiresAt
    );

    // Set cookie
    $value = $selector . '|' . $validator;
    $this->cookieManager->queue(
        $this->getRecallerName(),
        $value,
        $lifetime,
        null,
        null,
        config('session.secure', false), // secure
        true, // httpOnly
        'strict' // sameSite
    );
}

protected function userFromRecaller(array $recaller): ?Authenticatable
{
    [$selector, $validator] = $recaller;

    if (!$selector || !$validator) {
        return null;
    }

    // Retrieve token from database
    $rememberToken = $this->getRememberToken($selector);

    if (!$rememberToken) {
        return null;
    }

    // Check expiration
    if ($rememberToken['expires_at'] < time()) {
        $this->removeRememberToken($selector);
        return null;
    }

    // Verify validator
    $validatorHash = hash('sha256', $validator);
    if (!hash_equals($rememberToken['validator_hash'], $validatorHash)) {
        // Possible token theft - delete all tokens for this user
        $this->removeAllRememberTokens($rememberToken['user_id']);
        
        event(new CookieTheftDetected($rememberToken['user_id']));
        
        return null;
    }

    // Get user
    $user = $this->provider->retrieveById($rememberToken['user_id']);

    if ($user) {
        // Rotate token after successful use
        $this->removeRememberToken($selector);
        $this->createRememberMeCookie($user);
    }

    return $user;
}

protected function saveRememberToken(int $userId, string $selector, string $validatorHash, int $expiresAt): void
{
    DB::table('remember_tokens')->insert([
        'user_id' => $userId,
        'selector' => $selector,
        'validator_hash' => $validatorHash,
        'expires_at' => $expiresAt,
        'created_at' => time(),
    ]);
}

protected function getRememberToken(string $selector): ?array
{
    return DB::table('remember_tokens')
        ->where('selector', $selector)
        ->first();
}

protected function removeRememberToken(string $selector): void
{
    DB::table('remember_tokens')
        ->where('selector', $selector)
        ->delete();
}

protected function removeAllRememberTokens(int $userId): void
{
    DB::table('remember_tokens')
        ->where('user_id', $userId)
        ->delete();
}
```

**Create migration**:
```php
// database/migrations/xxxx_create_remember_tokens_table.php
Schema::create('remember_tokens', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('selector', 32)->unique();
    $table->string('validator_hash', 64);
    $table->integer('expires_at');
    $table->integer('created_at');
    
    $table->index(['selector', 'expires_at']);
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
});
```

---

## 🔧 MEDIUM PRIORITY FIXES

### Fix #9: Validate File Extensions

**File**: `Modules/Cms/Http/Controllers/MediaLibraryController.php`

**Update** upload method (line 119):
```php
// Define whitelist
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx'];
$allowedMimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

// Get and validate extension
$clientFilename = $file->getClientFilename();
$extension = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    return response()->json([
        'error' => 'File extension not allowed',
        'allowed' => implode(', ', $allowedExtensions)
    ], 400);
}

// Verify MIME type
$tempPath = $file->getStream()->getMetadata('uri');
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$actualMime = finfo_file($finfo, $tempPath);
finfo_close($finfo);

if (!isset($allowedMimeMap[$extension]) || $actualMime !== $allowedMimeMap[$extension]) {
    return response()->json([
        'error' => 'File MIME type mismatch',
        'expected' => $allowedMimeMap[$extension] ?? 'unknown',
        'actual' => $actualMime
    ], 400);
}

// Generate secure filename (remove original extension)
$filename = bin2hex(random_bytes(16)) . '.' . $extension;
```

---

### Fix #10: Secure Session Configuration

**File**: `config/session.php`

**Update**:
```php
return [
    'driver' => env('SESSION_DRIVER', 'database'),
    
    // Shorter lifetime for security (2 hours default)
    'lifetime' => env('SESSION_LIFETIME', 120),
    
    // Expire on close in production
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', !app()->isProduction()),
    
    // Cookie settings
    'cookie' => env('SESSION_COOKIE', 'bault_session'),
    
    // MUST be true in production
    'secure' => env('SESSION_SECURE_COOKIE', app()->isProduction()),
    
    // Prevent XSS
    'http_only' => true,
    
    // CSRF protection
    'same_site' => env('SESSION_SAME_SITE', 'strict'),
    
    // Regenerate session ID periodically
    'regenerate_on_activity' => true,
    'regenerate_interval' => 300, // 5 minutes
];
```

---

## 📝 Testing Security Fixes

### Create Security Test Suite

**File**: `tests/Security/SecurityTest.php`

```php
<?php

namespace Tests\Security;

use Tests\TestCase;

class SecurityTest extends TestCase
{
    /** @test */
    public function security_headers_are_present()
    {
        $response = $this->get('/');
        
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection');
        
        if (config('app.env') === 'production') {
            $response->assertHeader('Strict-Transport-Security');
        }
    }
    
    /** @test */
    public function csrf_protection_blocks_unverified_requests()
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        
        $response->assertStatus(419); // CSRF token mismatch
    }
    
    /** @test */
    public function rate_limiting_blocks_excessive_requests()
    {
        // Make 6 requests (limit is 5)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', [
                '_token' => csrf_token(),
                'email' => 'test@example.com',
                'password' => 'wrong',
            ]);
        }
        
        $this->assertEquals(429, $response->getStatusCode());
    }
    
    /** @test */
    public function path_traversal_is_blocked_in_uploads()
    {
        $maliciousPaths = [
            '../../../../etc/',
            '../../../config/',
            '..\\..\\..\\windows\\',
        ];
        
        foreach ($maliciousPaths as $path) {
            $response = $this->post('/media/upload', [
                'folder' => $path,
                'file' => $this->createTestImage(),
            ]);
            
            $response->assertStatus(400);
        }
    }
    
    /** @test */
    public function sql_injection_is_prevented()
    {
        $sqlPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE users--",
            "1 UNION SELECT * FROM users",
        ];
        
        foreach ($sqlPayloads as $payload) {
            $response = $this->get('/search?q=' . urlencode($payload));
            
            // Should not crash or expose data
            $response->assertSuccessful();
            $this->assertStringNotContainsString('DROP TABLE', $response->getContent());
        }
    }
    
    /** @test */
    public function xss_is_prevented_in_output()
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror="alert(1)">',
            'javascript:alert(1)',
        ];
        
        foreach ($xssPayloads as $payload) {
            $response = $this->post('/comments', [
                'content' => $payload,
            ]);
            
            $content = $response->getContent();
            
            // Should be escaped
            $this->assertStringNotContainsString('<script>', $content);
            $this->assertStringNotContainsString('onerror=', $content);
        }
    }
    
    /** @test */
    public function session_regenerates_on_login()
    {
        $oldSessionId = session()->getId();
        
        $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'user@example.com',
            'password' => 'password',
        ]);
        
        $newSessionId = session()->getId();
        
        $this->assertNotEquals($oldSessionId, $newSessionId);
    }
}
```

---

## 🚀 Deployment Checklist

Before deploying security fixes:

- [ ] Run full test suite: `php vendor/bin/phpunit`
- [ ] Run security tests: `php vendor/bin/phpunit tests/Security/`
- [ ] Check for regressions in existing features
- [ ] Update `.env.example` with new security settings
- [ ] Update documentation
- [ ] Notify users about security update
- [ ] Monitor logs after deployment

---

## 📚 Additional Resources

- [OWASP PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [Symfony Security Best Practices](https://symfony.com/doc/current/security.html)
- [Laravel Security Best Practices](https://laravel.com/docs/security)

---

**Last Updated**: 2026-01-19
