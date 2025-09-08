<?php

namespace Core\Contracts\Cache;

interface Factory
{
    /**
     * Get a cache store instance.
     *
     * @param  string|null  $name
     * @return \Psr\SimpleCache\CacheInterface
     */
    public function store($name = null);
}
