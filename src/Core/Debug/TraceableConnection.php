<?php

declare(strict_types=1);

namespace Core\Debug;

use Core\ORM\Connection;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;

/**
 * A decorator for the main Connection class that intercepts connection requests
 * to wrap the resulting PDO object in a TraceablePDO instance. This allows
 * the DebugBar to collect and display all SQL queries executed during a request.
 */
class TraceableConnection extends Connection
{
    /**
     * The original Connection instance being decorated.
     */
    protected Connection $decoratedConnection;

    /**
     * The PDOCollector from the DebugBar.
     */
    protected PDOCollector $pdoCollector;

    /**
     * TraceableConnection constructor.
     *
     * @param Connection   $decoratedConnection The original Connection instance.
     * @param PDOCollector $pdoCollector        The collector to which queries will be logged.
     */
    public function __construct(Connection $decoratedConnection, PDOCollector $pdoCollector)
    {
        // We don't call parent::__construct() because this is a decorator.
        // The decorated connection already has the app instance.
        $this->decoratedConnection = $decoratedConnection;
        $this->pdoCollector = $pdoCollector;
    }

    /**
     * Get a PDO connection instance, wrapped in a TraceablePDO.
     *
     * This method intercepts the call to the original `connection` method.
     * It retrieves the raw PDO object and wraps it in a TraceablePDO, which
     * logs all queries to the provided PDOCollector.
     *
     * @param string|null $name The connection name.
     * @param string      $type The connection type ('read' or 'write').
     * @return \PDO|\Swoole\Database\PDOProxy|TraceablePDO
     */
    public function connection(string $name = null, string $type = 'write'): mixed
    {
        $pdo = $this->decoratedConnection->connection($name, $type);

        // Only wrap standard PDO objects, not Swoole proxies which might have a different API.
        if ($pdo instanceof \PDO && !$pdo instanceof TraceablePDO) {
            $traceablePdo = new TraceablePDO($pdo);
            $this->pdoCollector->addConnection($traceablePdo, $name ?? 'default');
            return $traceablePdo;
        }

        return $pdo;
    }

    /**
     * Dynamically pass all other method calls to the original decorated Connection object.
     * This ensures that any method not overridden by this decorator (like getGrammar,
     * beginTransaction, commit, etc.) will function as expected.
     *
     * @param string $method
     * @param array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->decoratedConnection->{$method}(...$parameters);
    }
}
