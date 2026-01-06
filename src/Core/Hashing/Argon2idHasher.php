<?php

declare(strict_types=1);

namespace Core\Hashing;

use RuntimeException;

/**
 * Argon2id Hasher - Best password hashing algorithm
 * 
 * Argon2id combines the benefits of Argon2i (side-channel resistance) 
 * and Argon2d (GPU cracking resistance), making it the most secure option.
 * 
 * Winner of the Password Hashing Competition 2015.
 */
class Argon2idHasher implements HasherInterface
{
    public function __construct(protected array $options = [])
    {
    }

    public function make(string $value, array $options = []): string
    {
        // Add pepper if configured (server-side secret key)
        $value = $this->applyPepper($value);
        
        $hash = @password_hash($value, PASSWORD_ARGON2ID, $this->options($options));

        if ($hash === false) {
            throw new RuntimeException('Argon2id hashing failed. Ensure PASSWORD_ARGON2ID is supported.');
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
        return password_needs_rehash($hashedValue, PASSWORD_ARGON2ID, $this->options($options));
    }

    protected function options(array $options): array
    {
        return array_merge([
            'memory_cost' => $this->options['memory'] ?? PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => $this->options['time'] ?? PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => $this->options['threads'] ?? PASSWORD_ARGON2_DEFAULT_THREADS,
        ], $options);
    }

    /**
     * Apply pepper (server-side secret) to password before hashing.
     * This adds an extra layer of security even if the database is compromised.
     */
    protected function applyPepper(string $value): string
    {
        $pepper = $this->options['pepper'] ?? null;
        
        if (!$pepper) {
            return $value;
        }

        // Use HMAC-SHA256 to combine password and pepper
        // This is more secure than simple concatenation
        return hash_hmac('sha256', $value, $pepper);
    }
}

