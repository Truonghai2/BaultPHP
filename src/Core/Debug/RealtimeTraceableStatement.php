<?php

namespace Core\Debug;

/**
 * Wrapper cho PDOStatement để broadcast queries real-time.
 */
class RealtimeTraceableStatement extends \PDOStatement
{
    public function __construct(
        protected \PDOStatement $statement,
        protected string $sql,
        protected DebugBroadcaster $broadcaster,
    ) {
    }

    /**
     * Override execute để broadcast real-time.
     */
    public function execute($params = null): bool
    {
        $start = microtime(true);
        $result = $this->statement->execute($params);
        $duration = (microtime(true) - $start) * 1000;

        if ($this->broadcaster->isEnabled()) {
            $this->broadcaster->broadcastQuery([
                'sql' => $this->sql,
                'params' => $params ?? [],
                'duration_ms' => round($duration, 2),
                'success' => $result,
                'timestamp' => microtime(true),
            ]);
        }

        return $result;
    }

    /**
     * Delegate all other calls to wrapped statement.
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->statement->{$method}(...$args);
    }

    public function __get(string $name): mixed
    {
        return $this->statement->{$name};
    }

    public function __set(string $name, mixed $value): void
    {
        $this->statement->{$name} = $value;
    }
}
