<?php

declare(strict_types=1);

namespace Core\Hashing;

use Core\Application;
use Psr\Log\LoggerInterface;

/**
 * Adaptive Hash Manager with Risk-Based Hashing
 * 
 * This manager automatically adjusts hashing parameters based on:
 * - User risk level (admin, regular user, etc.)
 * - Security context (login from new device, suspicious activity)
 * - Performance requirements
 * 
 * Features:
 * - Risk-based parameter adjustment
 * - Timing attack protection
 * - Progressive rehashing
 * - Security monitoring
 */
class AdaptiveHashManager extends HashManager
{
    protected LoggerInterface $logger;
    protected array $riskProfiles;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->logger = $app->make(LoggerInterface::class);
        $this->loadRiskProfiles();
    }

    /**
     * Hash with risk-based parameters
     */
    public function makeWithRisk(string $value, string $riskLevel = 'standard', array $options = []): string
    {
        $profile = $this->getRiskProfile($riskLevel);
        $mergedOptions = array_merge($profile, $options);
        
        $startTime = microtime(true);
        $hash = $this->make($value, $mergedOptions);
        $duration = (microtime(true) - $startTime) * 1000;
        
        // Log if hashing takes too long
        if ($duration > 500) {
            $this->logger->warning('Slow password hashing detected', [
                'duration_ms' => $duration,
                'risk_level' => $riskLevel,
                'algorithm' => $this->getDefaultDriver(),
            ]);
        }
        
        return $hash;
    }

    /**
     * Check password with timing attack protection
     */
    public function checkSecure(string $value, string $hashedValue, array $options = []): bool
    {
        $startTime = microtime(true);
        
        // Always perform verification even for empty hash to prevent timing attacks
        $result = parent::check($value, $hashedValue, $options);
        
        // Add minimum processing time to prevent timing attacks
        // This makes all verifications take roughly the same time
        $minDuration = 0.05; // 50ms minimum
        $elapsed = microtime(true) - $startTime;
        
        if ($elapsed < $minDuration) {
            usleep((int)(($minDuration - $elapsed) * 1000000));
        }
        
        return $result;
    }

    /**
     * Check if hash should be upgraded based on risk level
     */
    public function needsUpgrade(string $hashedValue, string $currentRiskLevel = 'standard'): bool
    {
        // Always upgrade if needsRehash returns true
        if ($this->needsRehash($hashedValue)) {
            return true;
        }
        
        // Check if hash algorithm is outdated
        $info = $this->info($hashedValue);
        $algoName = $info['algoName'] ?? 'unknown';
        
        // Upgrade bcrypt or Argon2i to Argon2id
        if (in_array($algoName, ['bcrypt', 'argon2i'])) {
            return true;
        }
        
        return false;
    }

    /**
     * Get risk profile parameters
     */
    protected function getRiskProfile(string $level): array
    {
        return $this->riskProfiles[$level] ?? $this->riskProfiles['standard'];
    }

    /**
     * Load risk profiles from configuration
     */
    protected function loadRiskProfiles(): void
    {
        $this->riskProfiles = [
            // Low risk: Regular users, development environment
            'low' => [
                'memory_cost' => 32768,   // 32MB
                'time_cost' => 1,          // Fast
                'threads' => 1,
            ],
            
            // Standard risk: Most users
            'standard' => [
                'memory_cost' => 65536,   // 64MB
                'time_cost' => 2,          // Balanced
                'threads' => 1,
            ],
            
            // High risk: Admin users, privileged accounts
            'high' => [
                'memory_cost' => 131072,  // 128MB
                'time_cost' => 3,          // Slower but more secure
                'threads' => 2,
            ],
            
            // Critical: Super admins, system accounts
            'critical' => [
                'memory_cost' => 262144,  // 256MB
                'time_cost' => 4,          // Maximum security
                'threads' => 4,
            ],
        ];
    }

    /**
     * Monitor for suspicious hashing patterns
     */
    public function recordHashingAttempt(string $identifier, bool $success): void
    {
        $key = "hash_attempts:{$identifier}";
        $cache = $this->app->make('cache');
        
        $attempts = (int) $cache->get($key, 0);
        $attempts++;
        
        // Store for 1 hour
        $cache->put($key, $attempts, 3600);
        
        // Alert on suspicious activity
        if ($attempts > 10) {
            $this->logger->warning('Excessive password hashing attempts detected', [
                'identifier' => $identifier,
                'attempts' => $attempts,
                'timeframe' => '1 hour',
            ]);
        }
    }

    /**
     * Create Argon2id driver
     */
    protected function createArgon2idDriver(array $config): HasherInterface
    {
        return new Argon2idHasher($config);
    }
}

