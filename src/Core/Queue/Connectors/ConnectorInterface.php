<?php

namespace Core\Queue\Connectors;

use Core\Contracts\Queue\Queue;

interface ConnectorInterface
{
    public function connect(array $config): Queue;
}
