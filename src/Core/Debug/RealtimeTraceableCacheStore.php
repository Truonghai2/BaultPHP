<?php

namespace Core\Debug;

use Psr\SimpleCache\CacheInterface;

/**
 * Cache store wrapper với real-time broadcasting.
 */
class RealtimeTraceableCacheStore implements CacheInterface
{
    public function __construct(
        protected CacheInterface $store,
        protected DebugBroadcaster $broadcaster,
    ) {
    }

    public function get($key, $default = null): mixed
    {
        $value = $this->store->get($key, $default);
        $hit = ($value !== $default);
        
        $this->broadcaster->broadcastCache(
            $hit ? 'HIT' : 'MISS',
            $key,
            $hit ? $value : null
        );

        return $value;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $result = $this->store->set($key, $value, $ttl);
        
        $this->broadcaster->broadcastCache('WRITE', $key, $value);

        return $result;
    }

    public function delete($key): bool
    {
        $result = $this->store->delete($key);
        
        $this->broadcaster->broadcastCache('DELETE', $key);

        return $result;
    }

    public function clear(): bool
    {
        $result = $this->store->clear();
        
        $this->broadcaster->broadcastCache('CLEAR', '*');

        return $result;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $values = $this->store->getMultiple($keys, $default);
        
        foreach ($keys as $key) {
            $hit = is_array($values) 
                ? array_key_exists($key, $values) 
                : (isset($values[$key]) || array_key_exists($key, iterator_to_array($values)));
            
            $this->broadcaster->broadcastCache(
                $hit ? 'HIT' : 'MISS',
                $key,
                $hit ? ($values[$key] ?? null) : null
            );
        }

        return $values;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $result = $this->store->setMultiple($values, $ttl);
        
        foreach ($values as $key => $value) {
            $this->broadcaster->broadcastCache('WRITE', $key, $value);
        }

        return $result;
    }

    public function deleteMultiple($keys): bool
    {
        $result = $this->store->deleteMultiple($keys);
        
        foreach ($keys as $key) {
            $this->broadcaster->broadcastCache('DELETE', $key);
        }

        return $result;
    }

    public function has($key): bool
    {
        $result = $this->store->has($key);
        
        $this->broadcaster->broadcastCache(
            $result ? 'HIT' : 'MISS',
            $key
        );

        return $result;
    }
}

