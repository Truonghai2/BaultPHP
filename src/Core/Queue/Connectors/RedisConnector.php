<?php

namespace Core\Queue\Connectors;

use Core\Contracts\Queue\Queue;
use Core\Queue\RedisQueue;
use Core\Redis\RedisManager;

class RedisConnector implements ConnectorInterface
{
    protected RedisManager $redis;

    public function __construct(RedisManager $redis)
    {
        $this->redis = $redis;
    }

    public function connect(array $config): Queue
    {
        return new RedisQueue(
            $this->redis,
            $config['queue'] ?? 'default',
            $config['connection'] ?? 'default',
        );
    }
}
