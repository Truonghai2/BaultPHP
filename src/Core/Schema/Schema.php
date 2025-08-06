<?php

namespace Core\Schema;

use PDO;

class Schema
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $sql = $blueprint->getCreateSql();
        $this->pdo->exec($sql);
    }

    public function dropIfExists(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS `$table`";
        $this->pdo->exec($sql);
    }

    /**
     * Execute a raw SQL statement against the database.
     */
    public function statement(string $query): bool
    {
        return $this->pdo->exec($query) !== false;
    }
}
