<?php

namespace Core\View;

use ArrayAccess;
use IteratorAggregate;

class ComponentAttributeBag implements ArrayAccess, IteratorAggregate
{
    /**
     * The raw array of attributes.
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * Create a new component attribute bag instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get the values of the attributes that should be merged.
     *
     * @param  array  $defaults
     * @return static
     */
    public function merge(array $defaults = []): static
    {
        $attributes = array_merge($this->attributes, $defaults);

        foreach (array_keys($this->attributes) as $key) {
            if ($key === 'class' && isset($defaults['class'])) {
                $attributes['class'] = implode(' ', array_unique(array_filter([$defaults['class'], $this->attributes['class']])));
            }
        }

        return new static($attributes);
    }

    /**
     * Filter the attributes, returning a new bag with only the filtered attributes.
     *
     * @param  callable  $callback
     * @return static
     */
    public function filter(callable $callback): static
    {
        $filteredAttributes = [];

        foreach ($this->attributes as $key => $value) {
            if ($callback($key, $value)) {
                $filteredAttributes[$key] = $value;
            }
        }

        return new static($filteredAttributes);
    }

    /**
     * Filter the attributes to only include those that start with a given prefix.
     *
     * @param  string  $prefix
     * @return static
     */
    public function whereStartsWith(string $prefix): static
    {
        return $this->filter(fn ($key) => str_starts_with($key, $prefix));
    }

    /**
     * Get a given attribute from the attribute bag.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Determine if a given attribute exists in the attribute bag.
     *
     * @param  string  $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Render the attributes as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        $string = '';

        foreach ($this->attributes as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $string .= ' ' . $key;
            } else {
                $string .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false) . '"';
            }
        }

        return $string;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->attributes[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->attributes);
    }
}
