<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\Module\ModuleManager;

class ModuleManageCommand extends BaseCommand
{
    /**
     * Create a new command instance.
     *
     * The ModuleManager is injected automatically by the service container.
     */
    public function __construct(private ModuleManager $manager)
    {
        parent::__construct();
    }

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
            return 1;
        }

        if ($name = $this->option('enable')) {
            try {
                $this->manager->enable($name);
                $this->io->success("Module '{$name}' enabled successfully.");
                $this->io->info("Module cache cleared automatically. You may still need to clear other caches (e.g., `route:cache`).");
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());
            }
            return 0;
        }

        if ($name = $this->option('disable')) {
            try {
                $this->manager->disable($name);
                $this->io->success("Module '{$name}' disabled successfully.");
                $this->io->info("Module cache cleared automatically. You may still need to clear other caches (e.g., `route:cache`).");
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());
            }
            return 1;
        }

        $this->io->writeln("Please provide an option: --list, --enable=<ModuleName>, or --disable=<ModuleName>");
        return 1;
    }

    protected function displayModuleList(): void
    {
        $modules = $this->manager->getAllModules();
        if (empty($modules)) {
            $this->io->info("No modules found.");
            return;
        }

        $tableData = array_map(function ($module) {
            $status = $module['enabled'] ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>';
            return [$module['name'], $module['version'], $status];
        }, $modules);

        $this->io->table(['Module Name', 'Version', 'Status'], $tableData);
    }
}