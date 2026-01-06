<?php

namespace Core\Debug;

use PDO;
use PDOStatement;

/**
 * PDO Wrapper với real-time broadcasting capability.
 * Sử dụng composition thay vì inheritance để wrap PDO object.
 */
class RealtimeTraceablePdo
{
    protected PDO $pdo;
    protected ?DebugBroadcaster $broadcaster = null;

    /**
     * Constructor nhận PDO object để wrap.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Set debug broadcaster.
     */
    public function setBroadcaster(DebugBroadcaster $broadcaster): void
    {
        $this->broadcaster = $broadcaster;
    }

    /**
     * Wrap query execution để broadcast real-time.
     */
    public function query($statement, $mode = null, ...$fetch_mode_args): \PDOStatement|false
    {
        $start = microtime(true);
        
        // Call query() properly based on arguments provided
        if ($mode === null) {
            $result = $this->pdo->query($statement);
        } else {
            $result = $this->pdo->query($statement, $mode, ...$fetch_mode_args);
        }
        
        $duration = (microtime(true) - $start) * 1000;
        $this->broadcastQuery($statement, [], $duration, $result !== false);

        return $result;
    }

    /**
     * Wrap exec để broadcast real-time.
     */
    public function exec($statement): int|false
    {
        $start = microtime(true);
        $result = $this->pdo->exec($statement);
        $duration = (microtime(true) - $start) * 1000;

        $this->broadcastQuery($statement, [], $duration, $result !== false);

        return $result;
    }

    /**
     * Wrap prepare để wrap statement.
     */
    public function prepare($statement, $options = []): \PDOStatement|false
    {
        $stmt = $this->pdo->prepare($statement, $options);
        
        if ($stmt !== false && $this->broadcaster) {
            // Wrap statement để intercept execute
            $stmt = new RealtimeTraceableStatement($stmt, $statement, $this->broadcaster);
        }

        return $stmt;
    }

    /**
     * Delegate all other PDO methods to the wrapped PDO object.
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo->getAttribute($attribute);
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    public function errorCode(): ?string
    {
        return $this->pdo->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        return $this->pdo->quote($string, $type);
    }

    /**
     * Broadcast query information.
     */
    protected function broadcastQuery(string $sql, array $params, float $duration, bool $success): void
    {
        if (!$this->broadcaster || !$this->broadcaster->isEnabled()) {
            return;
        }

        $this->broadcaster->broadcastQuery([
            'sql' => $sql,
            'params' => $params,
            'duration_ms' => round($duration, 2),
            'success' => $success,
            'timestamp' => microtime(true),
        ]);
    }
}

