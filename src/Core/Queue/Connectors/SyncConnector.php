<?php

namespace Core\Queue\Connectors;

use Core\Contracts\Queue\Queue;
use Core\Queue\SyncQueue;

class SyncConnector implements ConnectorInterface
{
    public function connect(array $config): Queue
    {
        return new SyncQueue();
    }
}
