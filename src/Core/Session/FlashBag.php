<?php

namespace Core\Session;

/**
 * Manages flash messages, which are stored in the session for the next request only.
 *
 * This implementation is inspired by Symfony's AutoExpireFlashBag.
 * It separates "new" flashes (added during the current request) from "old" flashes
 * (from the previous request) to manage their lifecycle correctly.
 */
class FlashBag
{
    /**
     * Flashes from the previous request.
     * @var array
     */
    private array $oldFlashes = [];

    /**
     * Flashes for the next request.
     * @var array
     */
    private array $newFlashes = [];

    /**
     * @param array $storage The flash data from the session (e.g., $_SESSION['_flash']).
     */
    public function __construct(array $storage = [])
    {
        // Flashes from the previous request are now "old" and available for display.
        $this->oldFlashes = $storage;
    }

    /**
     * Adds a new flash message for the next request.
     */
    public function add(string $key, mixed $value): void
    {
        $this->newFlashes[$key][] = $value;
    }

    /**
     * Sets a flash message or messages for the next request.
     */
    public function set(string $key, mixed $value): void
    {
        $this->newFlashes[$key] = (array) $value;
    }

    /**
     * Peeks at the flash messages from the previous request without removing them.
     */
    public function peek(string $key, array $default = []): array
    {
        return $this->oldFlashes[$key] ?? $default;
    }

    /**
     * Gets flash messages from the previous request and removes them.
     */
    public function get(string $key, array $default = []): array
    {
        $value = $this->peek($key, $default);
        unset($this->oldFlashes[$key]);
        return $value;
    }

    /**
     * Gets all flash messages from the previous request and removes them.
     */
    public function all(): array
    {
        $all = $this->oldFlashes;
        $this->oldFlashes = [];
        return $all;
    }
}
