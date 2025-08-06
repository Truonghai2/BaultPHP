<?php

namespace Core;

use ArrayAccess;

class Config implements ArrayAccess
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function get(string $key, $default = null)
    {
        // Check if the exact key is already cached (e.g., from a previous set() call)
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        // The first segment of a dot-notation key is the config file name.
        $keys = explode('.', $key);
        $file = $keys[0];

        // Load the configuration file if it hasn't been loaded yet.
        if (!isset($this->items[$file])) {
            $path = base_path("config/{$file}.php");
            if (file_exists($path)) {
                $this->items[$file] = require $path;
            } else {
                // If the file doesn't exist, we can't find the key.
                return $default;
            }
        }

        // Traverse the keys to find the requested value.
        $value = $this->items;
        foreach ($keys as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
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
