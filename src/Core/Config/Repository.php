<?php

namespace Core\Config;

use ArrayAccess;
use Illuminate\Support\Arr;

/**
 * Class Repository
 *
 * Manages the application's configuration values.
 * Implements ArrayAccess to allow for array-like access to configuration.
 *
 * @implements ArrayAccess<string, mixed>
 */
class Repository implements ArrayAccess
{
    /**
     * All of the configuration items.
     *
     * @var array
     */
    protected array $items = [];

    /**
     * Create a new configuration repository.
     *
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this->items, $key, $default);
    }

    /**
     * Set a given configuration value.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value = null): void
    {
        Arr::set($this->items, $key, $value);
    }

    /**
     * Get all of the configuration items.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Determine if the given configuration option exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Get a configuration option.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Set a configuration option.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * Unset a configuration option.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->set($offset, null);
    }
}
