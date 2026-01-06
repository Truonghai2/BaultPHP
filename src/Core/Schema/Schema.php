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
    protected PDO|\Core\Debug\RealtimeTraceablePdo $pdo;
    protected Grammar $grammar;

    public function __construct(PDO|\Core\Debug\RealtimeTraceablePdo $pdo)
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

    /**
     * Check if a table exists in the database.
     */
    public function hasTable(string $table): bool
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            $query = match ($driver) {
                'mysql' => 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                'pgsql' => 'SELECT COUNT(*) FROM information_schema.tables WHERE table_catalog = current_database() AND table_name = ?',
                'sqlite' => "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
                'sqlsrv' => 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?',
                default => throw new \InvalidArgumentException("Unsupported database driver: {$driver}"),
            };

            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            // If there's any error checking, assume table doesn't exist
            return false;
        }
    }

    /**
     * Check if a column exists in a table.
     */
    public function hasColumn(string $table, string $column): bool
    {
        try {
            if (!$this->hasTable($table)) {
                return false;
            }

            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            $query = match ($driver) {
                'mysql' => 'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                'pgsql' => 'SELECT COUNT(*) FROM information_schema.columns WHERE table_catalog = current_database() AND table_name = ? AND column_name = ?',
                'sqlite' => 'SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?',
                'sqlsrv' => 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                default => throw new \InvalidArgumentException("Unsupported database driver: {$driver}"),
            };

            $stmt = $this->pdo->prepare($query);

            // SQLite uses different parameter order
            if ($driver === 'sqlite') {
                $stmt->execute([$table, $column]);
            } else {
                $stmt->execute([$table, $column]);
            }

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            // If there's any error checking, assume column doesn't exist to allow migration to proceed
            return false;
        }
    }

    /**
     * Get the connection (PDO instance).
     */
    public function getConnection(): PDO|\Core\Debug\RealtimeTraceablePdo
    {
        return $this->pdo;
    }

    protected function executeStatements(array|string $statements): void
    {
        foreach ((array) $statements as $statement) {
            $this->pdo->exec($statement);
        }
    }
}
