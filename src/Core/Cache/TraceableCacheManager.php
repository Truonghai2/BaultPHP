<?php

namespace Core\Cache;

use Core\Contracts\Cache\Factory;
use DebugBar\DebugBar;

/**
 * Class TraceableCacheManager
 *
 * Bọc (wraps) một CacheManager để trả về các cache store có thể theo dõi (traceable).
 */
class TraceableCacheManager implements Factory
{
    protected Factory $manager;
    protected DebugBar $debugbar;

    public function __construct(Factory $manager, DebugBar $debugbar)
    {
        $this->manager = $manager;
        $this->debugbar = $debugbar;
    }

    /**
     * {@inheritdoc}
     */
    public function store(string $name = null)
    {
        $store = $this->manager->store($name);

        if (!$this->debugbar->hasCollector('cache')) {
            $this->debugbar->addCollector(new \DebugBar\DataCollector\CacheCollector());
        }

        /** @var \DebugBar\DataCollector\CacheCollector $collector */
        $collector = $this->debugbar->getCollector('cache');

        return new TraceableCacheStore($store, $collector);
    }

    /**
     * Chuyển tiếp các lời gọi khác đến manager gốc.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->manager->{$method}(...$parameters);
    }
}
