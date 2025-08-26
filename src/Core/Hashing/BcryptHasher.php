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
}
