<?php

declare(strict_types=1);

namespace Core\Hashing;

use RuntimeException;

class BcryptHasher implements HasherInterface
{
    public function __construct(protected array $options = [])
    {
    }

    public function make(string $value, array $options = []): string
    {
        // Apply pepper (server-side secret) before hashing
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
        
        // Apply pepper before verification
        $value = $this->applyPepper($value);
        
        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->options($options)['rounds'],
        ]);
    }

    protected function options(array $options): array
    {
        return array_merge(['rounds' => $this->options['rounds'] ?? 12], $options);
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
