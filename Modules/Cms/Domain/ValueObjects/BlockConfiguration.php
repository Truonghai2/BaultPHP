<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\ValueObjects;

final class BlockConfiguration
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): self
    {
        $config = $this->config;
        $config[$key] = $value;

        return new self($config);
    }

    public function merge(array $config): self
    {
        return new self(array_merge($this->config, $config));
    }

    public function toArray(): array
    {
        return $this->config;
    }

    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function equals(BlockConfiguration $other): bool
    {
        return $this->config === $other->config;
    }
}
