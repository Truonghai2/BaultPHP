<?php

namespace App\Http\Controllers\Admin;

use Core\Exceptions\Module\ModuleNotFoundException;
use Core\FileSystem\Filesystem;
use Core\ORM\Connection;
use PDO;

class ModuleController
{
    protected string $modulesPath;
    protected PDO $pdo;

    public function __construct(
        protected Filesystem $fs,
        Connection $connection,
    ) {
        $this->modulesPath = base_path('Modules');
        $this->pdo = $connection->connection();
    }

    /**
     * Get a list of all modules from the filesystem and database.
     *
     * @return array
     */
    public function getModules(): array
    {
        $directories = $this->fs->directories($this->modulesPath);
        $installedModules = [];

        try {
            $stmt = $this->pdo->query('SELECT name, enabled, version, description FROM modules');
            $dbModules = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_GROUP) : [];
        } catch (\PDOException $e) {
            $dbModules = [];
        }

        foreach ($directories as $dir) {
            $name = basename($dir);
            $jsonPath = $dir . '/module.json';

            if (!$this->fs->exists($jsonPath) || !($meta = json_decode($this->fs->get($jsonPath), true))) {
                continue;
            }

            $installedModules[] = [
                'name' => $name,
                'version' => $dbModules[$name][0]['version'] ?? $meta['version'] ?? '1.0.0',
                'description' => $dbModules[$name][0]['description'] ?? $meta['description'] ?? 'No description provided.',
                'enabled' => isset($dbModules[$name]) ? (bool) $dbModules[$name][0]['enabled'] : ($meta['enabled'] ?? false),
            ];
        }

        return $installedModules;
    }

    /**
     * Toggles the enabled/disabled status of a module.
     *
     * @param string $moduleName
     * @return bool The new status of the module.
     * @throws ModuleNotFoundException
     */
    public function toggleStatus(string $moduleName): bool
    {
        $stmt = $this->pdo->prepare('SELECT enabled FROM modules WHERE name = ?');
        $stmt->execute([$moduleName]);
        $result = $stmt->fetchColumn();

        if ($result === false) {
            throw new ModuleNotFoundException("Module '{$moduleName}' not found in the database.");
        }

        $currentStatus = (bool) $result;
        $newStatus = !$currentStatus;

        $stmt = $this->pdo->prepare('UPDATE modules SET enabled = ? WHERE name = ?');
        $stmt->execute([$newStatus ? 1 : 0, $moduleName]);

        return $newStatus;
    }

    /**
     * Deletes a module completely.
     *
     * @throws ModuleNotFoundException
     */
    public function deleteModule(string $moduleName): void
    {
        $dir = $this->modulesPath . '/' . $moduleName;

        if (!$this->fs->isDirectory($dir)) {
            throw new ModuleNotFoundException("Module directory '{$moduleName}' does not exist.");
        }

        $stmt = $this->pdo->prepare('DELETE FROM modules WHERE name = ?');
        $stmt->execute([$moduleName]);

        $this->fs->deleteDirectory($dir);
    }
}
