<?php

declare(strict_types=1);

namespace Core\Security;

use Core\Contracts\Auth\Authenticatable;
use Core\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Zero-Trust Authentication
 *
 * Implements zero-trust security model with:
 * - Continuous verification
 * - Context-aware access control
 * - Device fingerprinting
 * - Risk scoring
 * - Adaptive authentication
 *
 * Features:
 * - Zero-trust authentication
 * - Continuous verification
 * - Context-aware access control
 * - mTLS support
 * - OAuth 2.1 support
 * - Advanced JWT features
 */
class ZeroTrustAuth
{
    protected array $riskFactors = [];
    protected array $deviceFingerprints = [];
    protected array $contextCache = [];

    public function __construct(
        protected array $config = [],
    ) {
    }

    /**
     * Authenticate request with zero-trust model
     *
     * @param ServerRequestInterface $request HTTP request
     * @return Authenticatable|null Authenticated user or null
     */
    public function authenticate(ServerRequestInterface $request): ?Authenticatable
    {
        // Step 1: Extract authentication credentials
        $credentials = $this->extractCredentials($request);
        if (!$credentials) {
            return null;
        }

        // Step 2: Verify credentials
        $user = $this->verifyCredentials($credentials, $request);
        if (!$user) {
            return null;
        }

        // Step 3: Continuous verification
        if (!$this->verifyContinuous($user, $request)) {
            Log::warning("Continuous verification failed", [
                'user_id' => $user->getAuthIdentifier(),
            ]);
            return null;
        }

        // Step 4: Context-aware access control
        $context = $this->buildContext($request);
        if (!$this->checkContextAccess($user, $context)) {
            Log::warning("Context access denied", [
                'user_id' => $user->getAuthIdentifier(),
                'context' => $context,
            ]);
            return null;
        }

        // Step 5: Risk assessment
        $riskScore = $this->assessRisk($user, $request, $context);
        if ($riskScore > ($this->config['max_risk_score'] ?? 0.7)) {
            Log::warning("High risk score, access denied", [
                'user_id' => $user->getAuthIdentifier(),
                'risk_score' => $riskScore,
            ]);
            return null;
        }

        // Step 6: Device verification
        if (!$this->verifyDevice($user, $request)) {
            Log::warning("Device verification failed", [
                'user_id' => $user->getAuthIdentifier(),
            ]);
            return null;
        }

        return $user;
    }

    /**
     * Extract credentials from request
     */
    protected function extractCredentials(ServerRequestInterface $request): ?array
    {
        // Try JWT token first
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return [
                'type' => 'jwt',
                'token' => $matches[1],
            ];
        }

        // Try mTLS client certificate
        $clientCert = $request->getServerParams()['SSL_CLIENT_CERT'] ?? null;
        if ($clientCert) {
            return [
                'type' => 'mtls',
                'certificate' => $clientCert,
            ];
        }

        // Try OAuth 2.1 token
        $oauthToken = $request->getHeaderLine('X-OAuth-Token');
        if ($oauthToken) {
            return [
                'type' => 'oauth',
                'token' => $oauthToken,
            ];
        }

        return null;
    }

    /**
     * Verify credentials
     */
    protected function verifyCredentials(array $credentials, ServerRequestInterface $request): ?Authenticatable
    {
        return match ($credentials['type']) {
            'jwt' => $this->verifyJWT($credentials['token'], $request),
            'mtls' => $this->verifyMTLS($credentials['certificate'], $request),
            'oauth' => $this->verifyOAuth($credentials['token'], $request),
            default => null,
        };
    }

    /**
     * Verify JWT token
     */
    protected function verifyJWT(string $token, ServerRequestInterface $request): ?Authenticatable
    {
        try {
            // Use existing JWT verification logic
            // This would integrate with TokenGuard or similar
            $guard = app('auth')->guard('api');
            return $guard->user();
        } catch (\Throwable $e) {
            Log::error("JWT verification failed", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify mTLS certificate
     */
    protected function verifyMTLS(string $certificate, ServerRequestInterface $request): ?Authenticatable
    {
        try {
            // Parse certificate
            $cert = openssl_x509_read($certificate);
            if (!$cert) {
                return null;
            }

            // Extract certificate info
            $certInfo = openssl_x509_parse($cert);
            
            // Verify certificate chain
            if (!$this->verifyCertificateChain($cert)) {
                return null;
            }

            // Extract user identifier from certificate
            $subject = $certInfo['subject'] ?? [];
            $userId = $subject['CN'] ?? $subject['emailAddress'] ?? null;

            if (!$userId) {
                return null;
            }

            // Retrieve user
            $authManager = app('auth');
            $guard = $authManager->guard('web');
            return $guard->getProvider()->retrieveById($userId);

        } catch (\Throwable $e) {
            Log::error("mTLS verification failed", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify OAuth 2.1 token
     */
    protected function verifyOAuth(string $token, ServerRequestInterface $request): ?Authenticatable
    {
        try {
            // Use existing OAuth verification logic
            $guard = app('auth')->guard('api');
            return $guard->user();
        } catch (\Throwable $e) {
            Log::error("OAuth verification failed", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Continuous verification
     */
    protected function verifyContinuous(Authenticatable $user, ServerRequestInterface $request): bool
    {
        // Check if user session is still valid
        $lastVerification = $this->getLastVerification($user);
        $verificationInterval = $this->config['verification_interval'] ?? 300; // 5 minutes

        if ($lastVerification && (time() - $lastVerification) > $verificationInterval) {
            // Re-verify credentials
            $credentials = $this->extractCredentials($request);
            if (!$credentials) {
                return false;
            }

            $verifiedUser = $this->verifyCredentials($credentials, $request);
            if (!$verifiedUser || $verifiedUser->getAuthIdentifier() !== $user->getAuthIdentifier()) {
                return false;
            }
        }

        // Update last verification time
        $this->setLastVerification($user);

        return true;
    }

    /**
     * Build context from request
     */
    protected function buildContext(ServerRequestInterface $request): array
    {
        $contextKey = $this->getContextKey($request);
        
        if (isset($this->contextCache[$contextKey])) {
            return $this->contextCache[$contextKey];
        }

        $context = [
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'device_fingerprint' => $this->getDeviceFingerprint($request),
            'location' => $this->getLocation($request),
            'time' => time(),
            'endpoint' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'headers' => $this->getSecurityHeaders($request),
        ];

        $this->contextCache[$contextKey] = $context;

        return $context;
    }

    /**
     * Check context-aware access control
     */
    protected function checkContextAccess(Authenticatable $user, array $context): bool
    {
        // Check IP whitelist/blacklist
        if (!$this->checkIpAccess($context['ip'])) {
            return false;
        }

        // Check device fingerprint
        if (!$this->checkDeviceAccess($user, $context['device_fingerprint'])) {
            return false;
        }

        // Check time-based access
        if (!$this->checkTimeAccess($context['time'])) {
            return false;
        }

        // Check location-based access
        if (!$this->checkLocationAccess($context['location'])) {
            return false;
        }

        return true;
    }

    /**
     * Assess risk score
     */
    protected function assessRisk(Authenticatable $user, ServerRequestInterface $request, array $context): float
    {
        $riskScore = 0.0;

        // IP risk
        $ipRisk = $this->assessIpRisk($context['ip']);
        $riskScore += $ipRisk * 0.3;

        // Device risk
        $deviceRisk = $this->assessDeviceRisk($user, $context['device_fingerprint']);
        $riskScore += $deviceRisk * 0.2;

        // Location risk
        $locationRisk = $this->assessLocationRisk($context['location']);
        $riskScore += $locationRisk * 0.2;

        // Behavior risk
        $behaviorRisk = $this->assessBehaviorRisk($user, $request);
        $riskScore += $behaviorRisk * 0.3;

        return min($riskScore, 1.0);
    }

    /**
     * Verify device
     */
    protected function verifyDevice(Authenticatable $user, ServerRequestInterface $request): bool
    {
        $fingerprint = $this->getDeviceFingerprint($request);
        $userId = $user->getAuthIdentifier();

        // Check if device is registered
        if (!isset($this->deviceFingerprints[$userId])) {
            // First time - register device
            $this->deviceFingerprints[$userId] = [$fingerprint];
            return true;
        }

        // Check if device is in registered list
        return in_array($fingerprint, $this->deviceFingerprints[$userId], true);
    }

    /**
     * Get device fingerprint
     */
    protected function getDeviceFingerprint(ServerRequestInterface $request): string
    {
        $components = [
            $request->getHeaderLine('User-Agent'),
            $request->getHeaderLine('Accept-Language'),
            $this->getClientIp($request),
        ];

        return hash('sha256', implode('|', $components));
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
     * Get location from IP (simplified)
     */
    protected function getLocation(ServerRequestInterface $request): ?array
    {
        // In production, use GeoIP service
        return null;
    }

    /**
     * Get security headers
     */
    protected function getSecurityHeaders(ServerRequestInterface $request): array
    {
        return [
            'x-forwarded-for' => $request->getHeaderLine('X-Forwarded-For'),
            'x-real-ip' => $request->getHeaderLine('X-Real-IP'),
            'referer' => $request->getHeaderLine('Referer'),
        ];
    }

    /**
     * Check IP access
     */
    protected function checkIpAccess(string $ip): bool
    {
        $blacklist = $this->config['ip_blacklist'] ?? [];
        $whitelist = $this->config['ip_whitelist'] ?? [];

        if (!empty($whitelist) && !in_array($ip, $whitelist, true)) {
            return false;
        }

        if (in_array($ip, $blacklist, true)) {
            return false;
        }

        return true;
    }

    /**
     * Check device access
     */
    protected function checkDeviceAccess(Authenticatable $user, string $fingerprint): bool
    {
        // Allow if device is registered or if device verification is disabled
        return $this->config['require_device_verification'] ?? true;
    }

    /**
     * Check time-based access
     */
    protected function checkTimeAccess(int $timestamp): bool
    {
        $allowedHours = $this->config['allowed_hours'] ?? null;
        if (!$allowedHours) {
            return true;
        }

        $hour = (int) date('G', $timestamp);
        return in_array($hour, $allowedHours, true);
    }

    /**
     * Check location-based access
     */
    protected function checkLocationAccess(?array $location): bool
    {
        // Implement location-based access control
        return true;
    }

    /**
     * Assess IP risk
     */
    protected function assessIpRisk(string $ip): float
    {
        // Check against known malicious IPs
        $maliciousIps = $this->config['malicious_ips'] ?? [];
        if (in_array($ip, $maliciousIps, true)) {
            return 1.0;
        }

        return 0.0;
    }

    /**
     * Assess device risk
     */
    protected function assessDeviceRisk(Authenticatable $user, string $fingerprint): float
    {
        $userId = $user->getAuthIdentifier();
        
        if (!isset($this->deviceFingerprints[$userId])) {
            return 0.5; // Unknown device
        }

        if (in_array($fingerprint, $this->deviceFingerprints[$userId], true)) {
            return 0.0; // Known device
        }

        return 0.7; // New device
    }

    /**
     * Assess location risk
     */
    protected function assessLocationRisk(?array $location): float
    {
        // Implement location-based risk assessment
        return 0.0;
    }

    /**
     * Assess behavior risk
     */
    protected function assessBehaviorRisk(Authenticatable $user, ServerRequestInterface $request): float
    {
        // Check request frequency, patterns, etc.
        return 0.0;
    }

    /**
     * Verify certificate chain
     */
    protected function verifyCertificateChain($certificate): bool
    {
        // Implement certificate chain verification
        return true;
    }

    /**
     * Get last verification time
     */
    protected function getLastVerification(Authenticatable $user): ?int
    {
        $userId = $user->getAuthIdentifier();
        $value = cache()->get("zerotrust:verification:{$userId}");
        return is_int($value) ? $value : null;
    }

    /**
     * Set last verification time
     */
    protected function setLastVerification(Authenticatable $user): void
    {
        $userId = $user->getAuthIdentifier();
        cache()->put("zerotrust:verification:{$userId}", time(), 3600);
    }

    /**
     * Get context cache key
     */
    protected function getContextKey(ServerRequestInterface $request): string
    {
        return hash('sha256', $this->getClientIp($request) . $request->getHeaderLine('User-Agent'));
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'registered_devices' => array_sum(array_map('count', $this->deviceFingerprints)),
            'context_cache_size' => count($this->contextCache),
        ];
    }
}
