<?php

namespace Core\Contracts\Queue;

interface Connector
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Core\Contracts\Queue\Queue
     */
    public function connect(array $config);
}
