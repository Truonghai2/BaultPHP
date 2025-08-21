<?php

namespace Core\Queue\Connectors;

use Core\Contracts\Queue\Connector as ConnectorContract;
use Core\Queue\Drivers\RabbitMQQueue;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQConnector implements ConnectorContract
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Core\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        $connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost'],
        );

        return new RabbitMQQueue(
            $connection,
            $config['queue'] ?? 'default',
            $config['options']['exchange'] ?? [],
        );
    }
}
