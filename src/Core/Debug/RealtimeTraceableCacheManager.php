<?php

namespace Core\Debug;

use Core\Cache\TraceableCacheStore;
use Core\Contracts\Cache\Factory;
use DebugBar\DebugBar;

/**
 * Cache Manager wrapper với real-time broadcasting.
 */
class RealtimeTraceableCacheManager implements Factory
{
    protected Factory $manager;
    protected DebugBar $debugbar;
    protected DebugBroadcaster $broadcaster;

    public function __construct(Factory $manager, DebugBar $debugbar, DebugBroadcaster $broadcaster)
    {
        $this->manager = $manager;
        $this->debugbar = $debugbar;
        $this->broadcaster = $broadcaster;
    }

    /**
     * {@inheritdoc}
     */
    public function store(string $name = null)
    {
        $store = $this->manager->store($name);

        // Ensure cache collector exists
        if (!$this->debugbar->hasCollector('cache')) {
            $this->debugbar->addCollector(new \DebugBar\DataCollector\CacheCollector());
        }

        /** @var \DebugBar\DataCollector\CacheCollector $collector */
        $collector = $this->debugbar->getCollector('cache');

        // Wrap với TraceableCacheStore cho DebugBar collection
        $traceableStore = new TraceableCacheStore($store, $collector);
        
        // Wrap thêm lần nữa với RealtimeTraceableCacheStore cho broadcasting
        return new RealtimeTraceableCacheStore($traceableStore, $this->broadcaster);
    }

    /**
     * Forward other calls to the original manager.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->manager->{$method}(...$parameters);
    }
}

