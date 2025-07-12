<?php

namespace Core\ORM;

use PDO;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * MigrationManager handles database migrations.
 */
class MigrationManager
{
    protected PDO $pdo;
    protected string $table = 'migrations';
    protected ?SymfonyStyle $io;

    public function __construct(PDO $pdo, ?SymfonyStyle $io = null)
    {
        $this->pdo = $pdo;
        $this->io = $io;
        $this->ensureTable();
    }

    protected function ensureTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
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
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
            $name = basename($file, '.php');

            require_once $file;

            $class = $this->findMigrationClass($file);
            if (!$class) {
                $this->log("<error>Migration class not found in $file</error>");
                continue;
            }

            $instance = new $class();
            $instance->up($this->pdo);
            $this->recordMigration($name, $batch);
            $this->log("Migrated: <comment>$name</comment>");
        }
    }

    protected function getAllMigrationFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            $globResult = glob($path . '/*.php');
            if ($globResult) {
                $files = array_merge($files, $globResult);
            }
        }
        sort($files); // Đảm bảo migration chạy theo thứ tự tên file
        return $files;
    }

    protected function findMigrationClass(string $file): ?string
    {
        $content = file_get_contents($file);
        $tokens = token_get_all($content);
        $class = null;
        $namespace = '';
        $namespaceFound = false;
        $classFound = false;

        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j] === ';') {
                        $namespaceFound = true;
                        break;
                    }
                    $namespace .= is_array($tokens[$j]) ? $tokens[$j][1] : '';
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                if (isset($tokens[$i + 2][1])) {
                    $class = $tokens[$i + 2][1];
                    $classFound = true;
                    break;
                }
            }
        }

        if (!$classFound) return null;

        return $namespaceFound ? trim($namespace) . '\\' . $class : $class;
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
