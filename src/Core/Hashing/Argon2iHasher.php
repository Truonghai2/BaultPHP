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
        $hash = password_hash($value, PASSWORD_ARGON2I, $this->options($options));

        if ($hash === false) {
            throw new RuntimeException('Argon2i hashing failed.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
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
}
