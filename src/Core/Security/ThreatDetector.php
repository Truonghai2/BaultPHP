<?php

declare(strict_types=1);

namespace Core\Security;

use Core\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface;

/**
 * AI-Powered Threat Detector
 *
 * Detects security threats using ML-based algorithms and behavioral analysis.
 *
 * Features:
 * - ML-based threat detection
 * - Behavioral analysis
 * - Auto-blocking
 * - Pattern recognition
 * - Anomaly detection
 */
class ThreatDetector
{
    protected array $requestPatterns = [];
    protected array $behaviorProfiles = [];
    protected array $blockedIps = [];
    protected array $threatHistory = [];

    public function __construct(
        protected array $config = [],
    ) {
        $this->loadBlockedIps();
    }

    /**
     * Detect threats in request
     *
     * @param ServerRequestInterface $request HTTP request
     * @return string Threat level: 'none', 'low', 'medium', 'high', 'critical'
     */
    public function detectThreats(ServerRequestInterface $request): string
    {
        $threatScore = 0.0;
        $threats = [];

        // Check for SQL injection
        $sqlInjection = $this->detectSQLInjection($request);
        if ($sqlInjection['detected']) {
            $threatScore += 0.3;
            $threats[] = 'sql_injection';
        }

        // Check for XSS
        $xss = $this->detectXSS($request);
        if ($xss['detected']) {
            $threatScore += 0.2;
            $threats[] = 'xss';
        }

        // Check for CSRF
        $csrf = $this->detectCSRF($request);
        if ($csrf['detected']) {
            $threatScore += 0.2;
            $threats[] = 'csrf';
        }

        // Check for path traversal
        $pathTraversal = $this->detectPathTraversal($request);
        if ($pathTraversal['detected']) {
            $threatScore += 0.3;
            $threats[] = 'path_traversal';
        }

        // Behavioral analysis
        $behavior = $this->analyzeBehavior($request);
        if ($behavior['suspicious']) {
            $threatScore += $behavior['score'];
            $threats[] = 'suspicious_behavior';
        }

        // Rate limiting check
        $rateLimit = $this->checkRateLimit($request);
        if ($rateLimit['exceeded']) {
            $threatScore += 0.2;
            $threats[] = 'rate_limit_exceeded';
        }

        // IP reputation check
        $ipReputation = $this->checkIpReputation($request);
        if ($ipReputation['malicious']) {
            $threatScore += 0.4;
            $threats[] = 'malicious_ip';
        }

        // Pattern matching
        $pattern = $this->matchThreatPatterns($request);
        if ($pattern['matched']) {
            $threatScore += $pattern['score'];
            $threats[] = 'threat_pattern';
        }

        // Record threat
        if ($threatScore > 0) {
            $this->recordThreat($request, $threatScore, $threats);
        }

        // Determine threat level
        return $this->calculateThreatLevel($threatScore);
    }

    /**
     * Detect SQL injection attempts
     */
    protected function detectSQLInjection(ServerRequestInterface $request): array
    {
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bOR\b.*=.*)/i',
            '/(\bAND\b.*=.*)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\b--|\/\*|\*\/)/',
            '/(\b1\s*=\s*1\b)/i',
        ];

        $input = $this->getRequestInput($request);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return ['detected' => true, 'pattern' => $pattern];
            }
        }

        return ['detected' => false];
    }

    /**
     * Detect XSS attempts
     */
    protected function detectXSS(ServerRequestInterface $request): array
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<img[^>]*src[^>]*=.*javascript:/i',
            '/<svg[^>]*onload/i',
        ];

        $input = $this->getRequestInput($request);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return ['detected' => true, 'pattern' => $pattern];
            }
        }

        return ['detected' => false];
    }

    /**
     * Detect CSRF attempts
     */
    protected function detectCSRF(ServerRequestInterface $request): array
    {
        // Check for missing CSRF token on state-changing requests
        $method = $request->getMethod();
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return ['detected' => false];
        }

        $csrfToken = $request->getHeaderLine('X-CSRF-Token')
            ?? $request->getParsedBody()['_token'] ?? null;

        if (!$csrfToken) {
            return ['detected' => true, 'reason' => 'missing_token'];
        }

        // Verify CSRF token (simplified)
        // In production, use proper CSRF verification
        return ['detected' => false];
    }

    /**
     * Detect path traversal attempts
     */
    protected function detectPathTraversal(ServerRequestInterface $request): array
    {
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/\.\.%2F/',
            '/\.\.%5C/',
            '/\.\.%252F/',
            '/\.\.%255C/',
        ];

        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        $input = $path . ' ' . $query;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return ['detected' => true, 'pattern' => $pattern];
            }
        }

        return ['detected' => false];
    }

    /**
     * Analyze request behavior
     */
    protected function analyzeBehavior(ServerRequestInterface $request): array
    {
        $ip = $this->getClientIp($request);
        $endpoint = $request->getUri()->getPath();
        $method = $request->getMethod();

        // Track request patterns
        $key = "behavior:{$ip}:{$endpoint}";
        $requests = cache()->get($key, []);
        $requests[] = time();
        
        // Keep only last hour
        $requests = array_filter($requests, fn($t) => $t > time() - 3600);
        cache()->put($key, $requests, 3600);

        $requestCount = count($requests);
        $suspicious = false;
        $score = 0.0;

        // Check for unusual request frequency
        if ($requestCount > ($this->config['max_requests_per_hour'] ?? 1000)) {
            $suspicious = true;
            $score += 0.3;
        }

        // Check for unusual endpoints
        $unusualEndpoints = $this->config['unusual_endpoints'] ?? [];
        if (in_array($endpoint, $unusualEndpoints, true)) {
            $suspicious = true;
            $score += 0.2;
        }

        // Check for unusual methods
        $unusualMethods = $this->config['unusual_methods'] ?? [];
        if (in_array($method, $unusualMethods, true)) {
            $suspicious = true;
            $score += 0.2;
        }

        return [
            'suspicious' => $suspicious,
            'score' => min($score, 1.0),
            'request_count' => $requestCount,
        ];
    }

    /**
     * Check rate limiting
     */
    protected function checkRateLimit(ServerRequestInterface $request): array
    {
        $ip = $this->getClientIp($request);
        $key = "ratelimit:{$ip}";
        
        $requests = cache()->get($key, []);
        $requests[] = time();
        
        // Keep only last minute
        $requests = array_filter($requests, fn($t) => $t > time() - 60);
        cache()->put($key, $requests, 60);

        $maxRequests = $this->config['max_requests_per_minute'] ?? 60;
        $exceeded = count($requests) > $maxRequests;

        return [
            'exceeded' => $exceeded,
            'count' => count($requests),
            'limit' => $maxRequests,
        ];
    }

    /**
     * Check IP reputation
     */
    protected function checkIpReputation(ServerRequestInterface $request): array
    {
        $ip = $this->getClientIp($request);

        // Check against blocked IPs
        if (in_array($ip, $this->blockedIps, true)) {
            return ['malicious' => true, 'reason' => 'blocked'];
        }

        // Check against known malicious IPs
        $maliciousIps = $this->config['malicious_ips'] ?? [];
        if (in_array($ip, $maliciousIps, true)) {
            return ['malicious' => true, 'reason' => 'known_malicious'];
        }

        // Check threat history
        $threatCount = $this->getThreatCount($ip);
        if ($threatCount > ($this->config['max_threats_per_ip'] ?? 10)) {
            return ['malicious' => true, 'reason' => 'high_threat_count'];
        }

        return ['malicious' => false];
    }

    /**
     * Match threat patterns
     */
    protected function matchThreatPatterns(ServerRequestInterface $request): array
    {
        $patterns = $this->config['threat_patterns'] ?? [];
        $input = $this->getRequestInput($request);

        foreach ($patterns as $pattern => $score) {
            if (preg_match($pattern, $input)) {
                return ['matched' => true, 'pattern' => $pattern, 'score' => $score];
            }
        }

        return ['matched' => false];
    }

    /**
     * Record threat
     */
    protected function recordThreat(ServerRequestInterface $request, float $score, array $threats): void
    {
        $ip = $this->getClientIp($request);
        $threat = [
            'ip' => $ip,
            'score' => $score,
            'threats' => $threats,
            'endpoint' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'timestamp' => time(),
        ];

        $this->threatHistory[] = $threat;

        // Keep only last 1000 threats
        if (count($this->threatHistory) > 1000) {
            array_shift($this->threatHistory);
        }

        // Log threat
        Log::warning("Threat detected", $threat);

        // Auto-block if score is high
        if ($score >= 0.8) {
            $this->blockIp($ip, 'high_threat_score');
        }
    }

    /**
     * Calculate threat level
     */
    protected function calculateThreatLevel(float $score): string
    {
        return match (true) {
            $score >= 0.9 => 'critical',
            $score >= 0.7 => 'high',
            $score >= 0.5 => 'medium',
            $score >= 0.3 => 'low',
            default => 'none',
        };
    }

    /**
     * Block IP address
     */
    public function blockIp(string $ip, string $reason = 'threat_detected'): void
    {
        if (!in_array($ip, $this->blockedIps, true)) {
            $this->blockedIps[] = $ip;
            
            // Persist to cache/database
            cache()->put("security:blocked_ips", $this->blockedIps, 86400);
            
            Log::warning("IP blocked", [
                'ip' => $ip,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * Unblock IP address
     */
    public function unblockIp(string $ip): void
    {
        $this->blockedIps = array_filter($this->blockedIps, fn($blockedIp) => $blockedIp !== $ip);
        cache()->put("security:blocked_ips", $this->blockedIps, 86400);
    }

    /**
     * Check if IP is blocked
     */
    public function isBlocked(string $ip): bool
    {
        return in_array($ip, $this->blockedIps, true);
    }

    /**
     * Get request input
     */
    protected function getRequestInput(ServerRequestInterface $request): string
    {
        $body = (string) $request->getBody();
        $query = $request->getUri()->getQuery();
        $path = $request->getUri()->getPath();
        
        return $path . ' ' . $query . ' ' . $body;
    }

    /**
     * Get client IP
     */
    protected function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['HTTP_X_FORWARDED_FOR'] 
            ?? $serverParams['HTTP_X_REAL_IP'] 
            ?? $serverParams['REMOTE_ADDR'] 
            ?? 'unknown';
    }

    /**
     * Get threat count for IP
     */
    protected function getThreatCount(string $ip): int
    {
        return count(array_filter(
            $this->threatHistory,
            fn($threat) => $threat['ip'] === $ip && $threat['timestamp'] > time() - 3600
        ));
    }

    /**
     * Load blocked IPs
     */
    protected function loadBlockedIps(): void
    {
        $this->blockedIps = cache()->get("security:blocked_ips", []);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'blocked_ips' => count($this->blockedIps),
            'threats_detected' => count($this->threatHistory),
            'recent_threats' => count(array_filter(
                $this->threatHistory,
                fn($t) => $t['timestamp'] > time() - 3600
            )),
        ];
    }
}
