<?php 

namespace Core\ORM;
use Core\ORM\Connection;
use PDO;

/**
 * QueryBuilder class for building and executing database queries.
 * 
 * This class provides methods to construct SQL queries with conditions,
 * pagination, and soft delete handling.
 */
class QueryBuilder
{
    protected string $modelClass;
    protected array $wheres = [];
    protected int $limit = 0;
    protected int $offset = 0;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function where(string $column, mixed $value): self
    {
        $this->wheres[] = [$column, $value];
        return $this;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $this->limit = $perPage;
        $this->offset = max(0, ($page - 1)) * $perPage;
        return $this->get();
    }

    public function first(): ?object
    {
        $this->limit = 1;
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function get(): array
    {
        $modelClass = $this->modelClass;

        if (!method_exists($modelClass, 'getTable')) {
            throw new \RuntimeException("Model $modelClass must implement getTable()");
        }

        $table = $modelClass::getTable();
        $bindings = [];
        $sql = "SELECT * FROM `$table`";

        if (!empty($this->wheres)) {
            $clauses = array_map(fn($w) => "`{$w[0]}` = ?", $this->wheres);
            $sql .= " WHERE " . implode(' AND ', $clauses);
            $bindings = array_column($this->wheres, 1);
        }

        if (method_exists($modelClass, 'getSoftDelete') && $modelClass::getSoftDelete()) {
            $sql .= (str_contains($sql, 'WHERE') ? " AND" : " WHERE") . " deleted_at IS NULL";
        }

        if ($this->limit > 0) {
            $sql .= " LIMIT {$this->limit} OFFSET {$this->offset}";
        }

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($bindings);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$modelClass, 'hydrate'], $rows);
    }
}