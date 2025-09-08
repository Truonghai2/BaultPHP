<?php

namespace Core\Schema;

use Core\Schema\Grammars\Grammar;
use Core\Schema\Grammars\MySqlGrammar;
use Core\Schema\Grammars\PostgresGrammar;
use Core\Schema\Grammars\SQLiteGrammar;
use Core\Schema\Grammars\SqlServerGrammar;
use PDO;

class Schema
{
    protected PDO $pdo;
    protected Grammar $grammar;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->grammar = $this->createGrammar();
    }

    protected function createGrammar(): Grammar
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'mysql' => new MySqlGrammar(),
            'pgsql' => new PostgresGrammar(),
            'sqlite' => new SQLiteGrammar(),
            'sqlsrv' => new SqlServerGrammar(),
            default => throw new \InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, $this->grammar);
        $callback($blueprint);
        $statements = $this->grammar->compileCreate($blueprint);
        $this->executeStatements($statements);
    }

    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, $this->grammar);
        $callback($blueprint);
        $statements = $this->grammar->compileAlter($blueprint);

        if (empty($statements)) {
            return;
        }

        foreach ($statements as $statement) {
            $this->pdo->exec($statement);
        }
    }

    public function dropIfExists(string $table): void
    {
        $blueprint = new Blueprint($table, $this->grammar);
        $statements = $this->grammar->compileDrop($blueprint);
        $this->executeStatements($statements);
    }

    /**
     * Execute a raw SQL statement against the database.
     */
    public function statement(string $query): bool
    {
        return $this->pdo->exec($query) !== false;
    }

    protected function executeStatements(array|string $statements): void
    {
        foreach ((array) $statements as $statement) {
            $this->pdo->exec($statement);
        }
    }
}
