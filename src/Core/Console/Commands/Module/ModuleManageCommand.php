<?php

namespace Core\Console\Commands\Module;

use Core\Console\Contracts\BaseCommand;

class ModuleManageCommand extends BaseCommand
{
    public function description(): string
    {
        return 'Manage application modules (list, enable, disable)';
    }

    public function signature(): string
    {
        return 'module:manage {--list : List all modules} {--enable= : Enable a module by name} {--disable= : Disable a module by name}';
    }

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->displayModuleList();
            return self::SUCCESS;
        }

        if ($name = $this->option('enable')) {
            return $this->setModuleStatus($name, true);
        }

        if ($name = $this->option('disable')) {
            return $this->setModuleStatus($name, false);
        }

        $this->io->writeln('Please provide an option: --list, --enable=<ModuleName>, or --disable=<ModuleName>');
        return self::FAILURE;
    }

    /**
     * Sets the enabled status of a module by modifying its module.json file.
     *
     * @param string $moduleName The name of the module.
     * @param bool $enabled The desired status (true for enabled, false for disabled).
     * @return int The command exit code.
     */
    protected function setModuleStatus(string $moduleName, bool $enabled): int
    {
        $moduleJsonPath = base_path("Modules/{$moduleName}/module.json");

        if (!file_exists($moduleJsonPath)) {
            $this->io->error("Module '{$moduleName}' not found at expected path: {$moduleJsonPath}");
            return self::FAILURE;
        }

        $moduleData = json_decode(file_get_contents($moduleJsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->io->error("Error decoding JSON for module '{$moduleName}'. Please check for syntax errors in module.json.");
            return self::FAILURE;
        }

        $moduleData['enabled'] = $enabled;

        file_put_contents(
            $moduleJsonPath,
            json_encode($moduleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        $status = $enabled ? 'enabled' : 'disabled';
        $this->io->success("Module '{$moduleName}' has been {$status}.");
        $this->io->info('Please clear relevant caches (e.g., `php cli cache:clear`) for the change to take full effect.');

        return self::SUCCESS;
    }

    protected function displayModuleList(): void
    {
        $modulePaths = glob(base_path('Modules/*/module.json'));
        if (empty($modulePaths)) {
            $this->io->info('No modules found.');
            return;
        }

        $tableData = [];
        foreach ($modulePaths as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (isset($data['name'])) {
                $status = ($data['enabled'] ?? false) ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>';
                $tableData[] = [
                    $data['name'],
                    $data['version'] ?? 'N/A',
                    $status,
                ];
            }
        }

        $this->io->table(['Module Name', 'Version', 'Status'], $tableData);
    }
}
