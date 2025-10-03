<?php

namespace Core\Database;

use DebugBar\DataCollector\PDO\PDOCollector;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Class TraceablePdo
 *
 * Lớp này kế thừa PDO và thêm vào khả năng theo dõi (tracing) các truy vấn
 * để tích hợp với PHP Debugbar.
 *
 * Nó ghi đè các phương thức thực thi truy vấn chính (`query`, `prepare`, `exec`)
 * để đo lường thời gian và ghi lại thông tin.
 */
class TraceablePdo extends PDO
{
    protected ?PDOCollector $collector = null;

    /**
     * @param string $dsn
     * @param string|null $username
     * @param string|null $password
     * @param array|null $options
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
    }

    /**
     * Thêm một collector để theo dõi các truy vấn.
     */
    public function addCollector(PDOCollector $collector): void
    {
        $this->collector = $collector;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): bool
    {
        return $this->traceCall(fn () => parent::beginTransaction(), 'beginTransaction');
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $statement): int|false
    {
        return $this->traceCall(fn () => parent::exec($statement), $statement);
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): PDOStatement|false
    {
        return $this->traceCall(fn () => parent::query($query, $fetchMode, ...$fetch_mode_args), $query);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $stmt = $this->traceCall(fn () => parent::prepare($query, $options), $query);

        if ($stmt && $this->collector) {
            return new TraceablePdoStatement($stmt, $this->collector);
        }

        return $stmt;
    }

    /**
     * Bọc một lời gọi đến PDO để theo dõi.
     */
    private function traceCall(\Closure $callback, string $sql)
    {
        if (!$this->collector) {
            return $callback();
        }

        $this->collector->startQuery($sql);
        try {
            $result = $callback();
        } catch (Throwable $e) {
            $this->collector->failQuery($e);
            throw $e;
        }
        $this->collector->endQuery();

        return $result;
    }
}
