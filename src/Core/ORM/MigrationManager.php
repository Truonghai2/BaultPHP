<?php

namespace Core\ORM;

use Core\Schema\Schema;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * MigrationManager handles database migrations.
 */
class MigrationManager
{
    protected \PDO|\Core\Debug\RealtimeTraceablePdo $pdo;
    protected string $table;
    protected ?SymfonyStyle $io;
    protected Schema $schema;

    /**
     * MigrationManager constructor.
     * @param \PDO $pdo
     * @param Schema $schema
     * @param string $migrationTable
     */
    public function __construct(\PDO|\Core\Debug\RealtimeTraceablePdo $pdo, Schema $schema, string $migrationTable = 'migrations')
    {
        $this->pdo = $pdo;
        $this->schema = $schema;
        $this->io = null; // Khởi tạo là null, sẽ được set sau qua setOutput()
        $this->table = $migrationTable;
        $this->ensureTable();
    }

    /**
     * Set the output interface for the manager.
     *
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @return void
     */
    public function setOutput(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    protected function ensureTable(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $idColumnDefinition = '';

        switch ($driver) {
            case 'pgsql':
                $idColumnDefinition = 'id SERIAL PRIMARY KEY';
                break;
            case 'sqlite':
                $idColumnDefinition = 'id INTEGER PRIMARY KEY AUTOINCREMENT';
                break;
            case 'sqlsrv':
                $idColumnDefinition = 'id INT IDENTITY(1,1) PRIMARY KEY';
                break;
            case 'mysql':
            default:
                $idColumnDefinition = 'id INT AUTO_INCREMENT PRIMARY KEY';
                break;
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                {$idColumnDefinition},
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // Add batch column if it doesn't exist for backward compatibility
        try {
            $this->pdo->query("SELECT batch FROM {$this->table} LIMIT 1");
        } catch (\PDOException $e) {
            $this->pdo->exec("ALTER TABLE {$this->table} ADD batch INT NOT NULL DEFAULT 1");
        }
    }

    public function getRan(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->table}");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function run(array $paths): void
    {
        $ran = $this->getRan();
        $allFiles = $this->getAllMigrationFiles($paths);

        $toRun = array_filter($allFiles, function ($file) use ($ran) {
            return !in_array(basename($file, '.php'), $ran);
        });

        if (empty($toRun)) {
            $this->log('<info>Nothing to migrate.</info>');
            return;
        }

        $batch = $this->getNextBatchNumber();

        foreach ($toRun as $file) {
            try {
                $name = basename($file, '.php');

                $instance = require $file;

                if (!is_object($instance)) {
                    $class = $this->findMigrationClass($file);
                    if (!$class) {
                        $this->log("<error>Migration class not found in '{$file}' and it did not return an object.</error>");
                        continue;
                    }
                    $instance = new $class();
                }

                if (!method_exists($instance, 'up')) {
                    $this->log("<error>Migration instance from '{$file}' does not have an 'up' method.</error>");
                    continue; // Skip this file and move to the next
                }

                $this->runUp($instance);
                $this->recordMigration($name, $batch);
                $this->log("Migrated: <comment>$name</comment>");
            } catch (\Throwable $e) {
                if ($e instanceof \PDOException && isset($e->errorInfo[1]) && $e->errorInfo[1] == 1050) {
                    $this->log('<warn>Skipped: ' . basename($file, '.php') . ' (Table already exists)</warn>');
                } elseif ($e instanceof \PDOException && isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [1366, 1292, 1406])) {
                    $this->log("<error>Data Error in migration file: {$file}</error>");
                    $this->log("<error>Message: {$e->getMessage()}</error>");
                    throw $e;
                } else {
                    $this->log("<error>FATAL ERROR in migration file: {$file}</error>");
                    throw $e;
                }
            }
        }
    }

    public function rollback(array $paths): void
    {
        $lastBatch = $this->getLastBatchNumber();

        if (is_null($lastBatch)) {
            $this->log('<info>Nothing to rollback.</info>');
            return;
        }

        $this->rollbackBatch($lastBatch, $paths);
    }

    public function reset(array $paths): void
    {
        $this->log('<info>Rolling back all migrations.</info>');

        $count = 0;
        while (!is_null($batch = $this->getLastBatchNumber())) {
            $count += $this->rollbackBatch($batch, $paths);
        }

        if ($count === 0) {
            $this->log('<info>Already at base state.</info>');
        }
    }

    protected function getAllMigrationFiles(array $paths): array
    {
        $defaultPath = base_path('database/migrations');
        if (is_dir($defaultPath) && !in_array($defaultPath, $paths, true)) {
            array_unshift($paths, $defaultPath);
        }

        $modulesPath = base_path('Modules');
        if (is_dir($modulesPath)) {
            $moduleDirs = glob($modulesPath . '/*', GLOB_ONLYDIR);
            foreach ($moduleDirs as $moduleDir) {
                $moduleMigrationPath = $moduleDir . '/Infrastructure/Migrations';
                $paths[] = $moduleMigrationPath;
            }
        }

        $files = [];
        foreach ($paths as $path){
            if (!is_dir($path)) {
                continue;
            }
            $files = array_merge($files, glob($path . '/[0-9]*.php'));
        }
        sort($files);
        return $files;
    }

    /**
     * Extracts the fully qualified class name from a PHP file.
     *
     * @param string $file The full path to the PHP file.
     * @return string|null The FQCN or null if not found.
     */
    protected function findMigrationClass(string $file): ?string
    {
        $content = file_get_contents($file);
        $namespace = null;
        $class = null;

        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/^class\s+([^\s{]+)/m', $content, $matches)) {
            $class = $matches[1];
        }

        return ($namespace && $class) ? $namespace . '\\' . $class : $class;
    }

    protected function recordMigration(string $name, int $batch): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)");
        $stmt->execute([$name, $batch]);
    }

    protected function getNextBatchNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM {$this->table}");
        return ($stmt->fetchColumn() ?: 0) + 1;
    }

    protected function getLastBatchNumber(): ?int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM {$this->table}");
        $result = $stmt->fetchColumn();
        return $result ? (int)$result : null;
    }

    protected function getMigrationsForBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare("SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY id DESC");
        $stmt->execute([$batch]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    protected function deleteMigrationRecord(string $name): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE migration = ?");
        $stmt->execute([$name]);
    }

    /**
     * Rolls back a specific batch of migrations.
     *
     * @param int $batch
     * @param array $paths
     * @return int The number of migrations rolled back.
     */
    protected function rollbackBatch(int $batch, array $paths): int
    {
        $migrationsToRollback = $this->getMigrationsForBatch($batch);

        if (empty($migrationsToRollback)) {
            $this->log("<info>No migrations found in batch {$batch}.</info>");
            return 0;
        }

        $this->log("<info>Rolling back batch:</info> {$batch}");

        $filesMap = [];
        foreach ($this->getAllMigrationFiles($paths) as $file) {
            $filesMap[basename($file, '.php')] = $file;
        }

        foreach ($migrationsToRollback as $migrationName) {
            if (isset($filesMap[$migrationName])) {
                $file = $filesMap[$migrationName];
                $instance = require $file;

                if (!is_object($instance)) {
                    $class = $this->findMigrationClass($file);
                    if (!$class) {
                        $this->log("<error>Could not find a class in migration file: {$file}</error>");
                        continue;
                    }
                    $instance = new $class();
                }

                if (method_exists($instance, 'down')) {
                    $this->runDown($instance);
                    $this->deleteMigrationRecord($migrationName);
                    $this->log("Rolled back: <comment>{$migrationName}</comment>");
                }
            } else {
                $this->log("<error>Migration file for '{$migrationName}' not found. Skipping.</error>");
            }
        }
        return count($migrationsToRollback);
    }

    protected function runUp(object $instance): void
    {
        if ($instance instanceof \Core\Schema\Migration) {
            $instance->setSchema($this->schema);
            $instance->up();
        } else {
            $instance->up($this->pdo);
        }
    }

    protected function runDown(object $instance): void
    {
        if ($instance instanceof \Core\Schema\Migration) {
            $instance->setSchema($this->schema);
            $instance->down();
        } else {
            $instance->down($this->pdo);
        }
    }

    protected function log(?string $message): void
    {
        if ($this->io && $message) {
            $this->io->writeln($message);
        } elseif ($message) {
            // Fallback for non-console environments like tests
            // echo $message . PHP_EOL; // Uncomment for debugging tests
        }
    }
}
