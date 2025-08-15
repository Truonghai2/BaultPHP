<?php

namespace Core;

use ArrayAccess;

class Config implements ArrayAccess
{
    protected array $items = [];
    protected bool $loadedFromCache = false;
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $cachedConfigPath = $this->app->bootstrapPath('cache/config.php');

        if (file_exists($cachedConfigPath)) {
            $this->items = require $cachedConfigPath;
            $this->loadedFromCache = true;
        }
    }

    public function get(string $key, $default = null)
    {
        // If not loaded from cache, perform on-demand file loading for the top-level key.
        if (!$this->loadedFromCache) {
            $keys = explode('.', $key);
            $file = $keys[0];

            if (!isset($this->items[$file])) {
                $path = $this->app->basePath("config/{$file}.php");
                if (file_exists($path)) {
                    $this->items[$file] = require $path;
                }
            }
        }

        // Traversal logic works for both cached (all items pre-loaded)
        // and on-demand (items loaded as needed).
        if (isset($this->items[$key])) {
            return $this->items[$key];
        }

        if (!str_contains($key, '.')) {
            return $this->items[$key] ?? $default;
        }

        $array = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }
        return $array;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $array = &$this->items;

        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }

    public function offsetExists($offset): bool
    {
        return $this->get($offset) !== null;
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->set($offset, null);
    }
}
