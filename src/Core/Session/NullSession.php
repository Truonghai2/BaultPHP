<?php

namespace Core\Session;

use Core\Contracts\Session\SessionInterface;

/**
 * Stub session used when resolving the real session would cause a circular dependency.
 * In-memory only; no persistence. Safe for exception handling / error responses.
 */
final class NullSession implements SessionInterface
{
    private string $id;
    private string $name = 'bault_session';
    private array $attributes = [];
    private bool $started = false;
    private FlashBag $flashBag;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(20));
        $this->flashBag = new FlashBag([]);
    }

    public function start(): bool
    {
        $this->started = true;
        return true;
    }

    public function save(): void
    {
    }

    public function all(): array
    {
        return $this->attributes;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    public function flush(): void
    {
        $this->attributes = [];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function regenerate(bool $destroy = false): bool
    {
        $this->id = bin2hex(random_bytes(20));
        return true;
    }

    public function invalidate(): bool
    {
        $this->flush();
        $this->id = bin2hex(random_bytes(20));
        return true;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getFlashBag(): FlashBag
    {
        return $this->flashBag;
    }
}
