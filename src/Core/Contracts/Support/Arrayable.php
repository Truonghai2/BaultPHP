<?php

namespace Core\Contracts\Support;

/**
 * Arrayable is an interface that defines a method to convert an object to an array.
 * This is useful for objects that need to be represented as arrays, such as in JSON responses.
 */
interface Arrayable
{
    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array;
}
