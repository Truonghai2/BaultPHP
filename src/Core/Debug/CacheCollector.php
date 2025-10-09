<?php

declare(strict_types=1);

namespace Core\Debug;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Collects data about cache usage.
 */
class CacheCollector extends DataCollector implements Renderable
{
    protected int $hits = 0;
    protected int $misses = 0;
    protected int $writes = 0;
    protected int $forgets = 0;
    protected array $events = [];

    /**
     * Record a cache hit.
     */
    public function addHit(string $key, string $store): void
    {
        $this->hits++;
        $this->addEvent('hit', "Cache hit for key '{$key}' on store '{$store}'.");
    }

    /**
     * Record a cache miss.
     */
    public function addMiss(string $key, string $store): void
    {
        $this->misses++;
        $this->addEvent('miss', "Cache miss for key '{$key}' on store '{$store}'.");
    }

    /**
     * Record a cache write.
     */
    public function addWrite(string $key, mixed $value, ?int $ttl, string $store): void
    {
        $this->writes++;
        $ttlString = is_null($ttl) ? 'forever' : "{$ttl}s";
        $this->addEvent('write', "Wrote key '{$key}' to store '{$store}' (TTL: {$ttlString}).");
    }

    /**
     * Record a cache forget.
     */
    public function addForget(string $key, string $store): void
    {
        $this->forgets++;
        $this->addEvent('forget', "Forgot key '{$key}' from store '{$store}'.");
    }

    /**
     * Add a generic cache event to the timeline.
     */
    protected function addEvent(string $label, string $message): void
    {
        $this->events[] = [
            'label' => $label,
            'message' => $message,
            'is_string' => true,
            'time' => microtime(true),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'writes' => $this->writes,
            'forgets' => $this->forgets,
            'events' => $this->events,
            'total' => $this->hits + $this->misses,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'cache';
    }

    /**
     * {@inheritdoc}
     */
    public function getWidgets(): array
    {
        return [
            'cache' => [
                'icon' => 'hdd-o',
                'widget' => 'PhpDebugBar.Widgets.TimelineWidget',
                'map' => 'cache.events',
                'default' => '[]',
            ],
            'cache:badge' => [
                'map' => 'cache.total',
                'default' => 0,
            ],
        ];
    }
}
