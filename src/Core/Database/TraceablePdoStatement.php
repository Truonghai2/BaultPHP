<?php

namespace Core\Database;

use DebugBar\DataCollector\PDO\PDOCollector;
use PDOStatement;
use Throwable;

/**
 * Class TraceablePdoStatement
 *
 * Bọc một PDOStatement để theo dõi (trace) việc thực thi của nó.
 */
class TraceablePdoStatement extends PDOStatement
{
    protected PDOStatement $statement;
    protected PDOCollector $collector;
    protected array $boundParameters = [];

    public function __construct(PDOStatement $statement, PDOCollector $collector)
    {
        $this->statement = $statement;
        $this->collector = $collector;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length = null, $driver_options = null): bool
    {
        $this->boundParameters[$parameter] = &$variable;
        return $this->statement->bindParam($parameter, $variable, $data_type, $length, $driver_options);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR): bool
    {
        $this->boundParameters[$parameter] = $value;
        return $this->statement->bindValue($parameter, $value, $data_type);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): bool
    {
        $this->collector->startQuery($this->statement->queryString, $params ?? $this->boundParameters);
        try {
            $result = $this->statement->execute($params);
        } catch (Throwable $e) {
            $this->collector->failQuery($e);
            throw $e;
        }
        $this->collector->endQuery();

        return $result;
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->statement, $method], $args);
    }
}
