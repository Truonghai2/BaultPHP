# Security Enhancements Guide

## Tổng quan

Hệ thống Security Enhancements đã được triển khai với:

1. **Zero-Trust Architecture** - Continuous verification và context-aware access control
2. **AI-Powered Threat Detection** - ML-based threat detection và auto-blocking

## 1. Zero-Trust Architecture

### Cấu hình

Thêm vào `.env`:
```env
ZEROTRUST_ENABLED=true
ZEROTRUST_VERIFICATION_INTERVAL=300
ZEROTRUST_MAX_RISK_SCORE=0.7
ZEROTRUST_REQUIRE_DEVICE=true
ZEROTRUST_IP_WHITELIST=192.168.1.1,10.0.0.1
ZEROTRUST_IP_BLACKLIST=
ZEROTRUST_MALICIOUS_IPS=
ZEROTRUST_ALLOWED_HOURS=0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23
```

### Features

- ✅ **Zero-trust authentication** - Never trust, always verify
- ✅ **Continuous verification** - Re-verify credentials periodically
- ✅ **Context-aware access control** - Access based on context
- ✅ **Device fingerprinting** - Track and verify devices
- ✅ **Risk scoring** - Assess risk for each request
- ✅ **mTLS support** - Mutual TLS authentication
- ✅ **OAuth 2.1 support** - OAuth 2.1 token verification
- ✅ **Advanced JWT** - Enhanced JWT features

### Sử dụng

#### Basic Authentication

```php
use Core\Security\ZeroTrustAuth;

$zerotrust = app(ZeroTrustAuth::class);

// Authenticate request
$user = $zerotrust->authenticate($request);

if (!$user) {
    return response()->json(['error' => 'Unauthorized'], 401);
}

// User is authenticated with zero-trust model
```

#### Middleware Integration

```php
use Core\Security\ZeroTrustAuth;

class ZeroTrustMiddleware
{
    public function handle($request, $next, ZeroTrustAuth $zerotrust)
    {
        $user = $zerotrust->authenticate($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // Set authenticated user
        Auth::setUser($user);
        
        return $next($request);
    }
}
```

### Authentication Methods

#### JWT Token

```http
Authorization: Bearer <jwt_token>
```

#### mTLS Certificate

Client certificate được tự động verify từ SSL_CLIENT_CERT header.

#### OAuth 2.1 Token

```http
X-OAuth-Token: <oauth_token>
```

### Risk Assessment

Risk score được tính dựa trên:
- IP reputation (30%)
- Device verification (20%)
- Location (20%)
- Behavior patterns (30%)

Risk levels:
- **Low** (< 0.3): Normal access
- **Medium** (0.3-0.5): Additional verification
- **High** (0.5-0.7): Restricted access
- **Critical** (> 0.7): Access denied

### Context-Aware Access Control

Access được kiểm tra dựa trên:
- IP whitelist/blacklist
- Device fingerprint
- Time-based access
- Location-based access

## 2. AI-Powered Threat Detection

### Cấu hình

Thêm vào `.env`:
```env
THREAT_DETECTION_ENABLED=true
THREAT_MAX_REQUESTS_PER_MINUTE=60
THREAT_MAX_REQUESTS_PER_HOUR=1000
THREAT_MAX_THREATS_PER_IP=10
THREAT_AUTO_BLOCK=true
THREAT_MALICIOUS_IPS=
THREAT_UNUSUAL_ENDPOINTS=
THREAT_UNUSUAL_METHODS=
```

### Features

- ✅ **ML-based detection** - Statistical threat detection
- ✅ **Behavioral analysis** - Analyze request patterns
- ✅ **Auto-blocking** - Automatically block malicious IPs
- ✅ **Pattern recognition** - Detect known threat patterns
- ✅ **SQL injection detection** - Detect SQL injection attempts
- ✅ **XSS detection** - Detect XSS attempts
- ✅ **CSRF detection** - Detect CSRF attempts
- ✅ **Path traversal detection** - Detect path traversal attempts

### Sử dụng

#### Basic Threat Detection

```php
use Core\Security\ThreatDetector;

$detector = app(ThreatDetector::class);

// Detect threats in request
$threatLevel = $detector->detectThreats($request);

if ($threatLevel !== 'none') {
    Log::warning("Threat detected", [
        'level' => $threatLevel,
        'ip' => $request->ip(),
    ]);
    
    if ($threatLevel === 'critical') {
        return response()->json(['error' => 'Request blocked'], 403);
    }
}
```

#### Middleware Integration

```php
use Core\Security\ThreatDetector;

class ThreatDetectionMiddleware
{
    public function handle($request, $next, ThreatDetector $detector)
    {
        $threatLevel = $detector->detectThreats($request);
        
        if ($threatLevel === 'critical' || $threatLevel === 'high') {
            return response()->json([
                'error' => 'Request blocked',
                'reason' => 'Threat detected',
            ], 403);
        }
        
        return $next($request);
    }
}
```

### Threat Levels

- **none** - No threats detected
- **low** - Minor threats, monitor
- **medium** - Moderate threats, additional verification
- **high** - Significant threats, restrict access
- **critical** - Critical threats, block immediately

### Detected Threats

1. **SQL Injection** - Detected SQL injection patterns
2. **XSS** - Detected XSS attack patterns
3. **CSRF** - Missing or invalid CSRF tokens
4. **Path Traversal** - Directory traversal attempts
5. **Suspicious Behavior** - Unusual request patterns
6. **Rate Limit Exceeded** - Too many requests
7. **Malicious IP** - Known malicious IP address
8. **Threat Pattern** - Matched known threat pattern

### Auto-Blocking

Khi threat score >= 0.8, IP sẽ tự động bị block:

```php
// IP is automatically blocked
$detector->blockIp($ip, 'high_threat_score');

// Check if IP is blocked
if ($detector->isBlocked($ip)) {
    return response()->json(['error' => 'IP blocked'], 403);
}

// Unblock IP manually
$detector->unblockIp($ip);
```

## Examples

### Example 1: Zero-Trust Authentication trong Controller

```php
use Core\Security\ZeroTrustAuth;

class ApiController
{
    public function index(Request $request, ZeroTrustAuth $zerotrust)
    {
        $user = $zerotrust->authenticate($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // User is authenticated with zero-trust
        return response()->json(['data' => 'protected data']);
    }
}
```

### Example 2: Threat Detection trong Middleware

```php
use Core\Security\ThreatDetector;

class SecurityMiddleware
{
    public function handle($request, $next, ThreatDetector $detector)
    {
        // Detect threats
        $threatLevel = $detector->detectThreats($request);
        
        // Block critical threats
        if ($threatLevel === 'critical') {
            return response()->json([
                'error' => 'Request blocked',
                'reason' => 'Security threat detected',
            ], 403);
        }
        
        // Log high threats
        if ($threatLevel === 'high') {
            Log::warning("High threat detected", [
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
            ]);
        }
        
        return $next($request);
    }
}
```

### Example 3: Combined Zero-Trust và Threat Detection

```php
use Core\Security\ZeroTrustAuth;
use Core\Security\ThreatDetector;

class SecureApiMiddleware
{
    public function handle($request, $next, ZeroTrustAuth $zerotrust, ThreatDetector $detector)
    {
        // First, detect threats
        $threatLevel = $detector->detectThreats($request);
        
        if ($threatLevel !== 'none') {
            return response()->json(['error' => 'Threat detected'], 403);
        }
        
        // Then, authenticate with zero-trust
        $user = $zerotrust->authenticate($request);
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        Auth::setUser($user);
        
        return $next($request);
    }
}
```

### Example 4: Custom Threat Patterns

```php
// In config/security.php
'threat_detection' => [
    'threat_patterns' => [
        '/admin.*delete/i' => 0.8, // High score for admin delete operations
        '/\.\.\/\.\.\//' => 0.9, // Very high score for path traversal
        '/<script/i' => 0.7, // High score for script tags
    ],
],
```

## Best Practices

### Zero-Trust

1. **Continuous Verification**: Set appropriate verification interval
2. **Risk Scoring**: Tune risk thresholds based on your needs
3. **Device Management**: Register trusted devices
4. **IP Management**: Maintain IP whitelist/blacklist
5. **Context Awareness**: Use context for access decisions

### Threat Detection

1. **Pattern Updates**: Regularly update threat patterns
2. **Rate Limiting**: Set appropriate rate limits
3. **Auto-Blocking**: Enable auto-blocking for critical threats
4. **Monitoring**: Monitor threat statistics
5. **False Positives**: Tune thresholds to reduce false positives

## Troubleshooting

### Zero-Trust Issues

**Users can't authenticate:**
- Check verification interval
- Verify risk score thresholds
- Check device registration
- Verify IP whitelist/blacklist

**High false positives:**
- Adjust risk score thresholds
- Update device fingerprints
- Review IP whitelist

### Threat Detection Issues

**Too many false positives:**
- Adjust threat patterns
- Tune rate limits
- Review threat thresholds

**Missing threats:**
- Update threat patterns
- Review detection logic
- Check rate limits

## Performance Tips

1. **Caching**: Cache device fingerprints và context
2. **Async Processing**: Process threat detection async when possible
3. **Rate Limiting**: Use efficient rate limiting algorithms
4. **Pattern Matching**: Optimize pattern matching
5. **IP Lookup**: Cache IP reputation lookups

## Kết luận

Security Enhancements cung cấp:

- ✅ **Zero-trust authentication** với continuous verification
- ✅ **Context-aware access control** dựa trên context
- ✅ **AI-powered threat detection** với ML algorithms
- ✅ **Auto-blocking** cho malicious IPs
- ✅ **Multiple authentication methods** (JWT, mTLS, OAuth 2.1)
- ✅ **Easy integration** với existing codebase

Enable các features theo nhu cầu và security requirements.
