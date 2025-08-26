<?php

namespace Core\Debug;

use PDO;
use PDOStatement;

/**
 * Một lớp PDO wrapper để ghi lại các truy vấn SQL.
 * Nó ủy quyền tất cả các lệnh gọi đến đối tượng PDO thực sự.
 */
class TraceablePdo extends PDO
{
    private PDO $pdo;
    private DebugManager $debugManager;

    public function __construct(PDO $pdo, DebugManager $debugManager)
    {
        $this->pdo = $pdo;
        $this->debugManager = $debugManager;
    }

    private function recordQuery(string $statement, float $startTime, ?array $params = null): void
    {
        $duration = (microtime(true) - $startTime) * 1000; // ms
        $this->debugManager->add('queries', [
            'sql' => $statement,
            'params' => $params,
            'duration_ms' => round($duration, 2),
            'connection' => 'default', // Có thể mở rộng để lấy tên connection
        ]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return $this->pdo->prepare($query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): PDOStatement|false
    {
        $startTime = microtime(true);
        $result = $this->pdo->query($query, $fetchMode, ...$fetch_mode_args);
        $this->recordQuery($query, $startTime);
        return $result;
    }

    public function exec(string $statement): int|false
    {
        $startTime = microtime(true);
        $result = $this->pdo->exec($statement);
        $this->recordQuery($statement, $startTime);
        return $result;
    }

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
}
