<?php

declare(strict_types=1);

namespace Modules\Admin\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Admin\Application\Services\ModuleAggregateService;

/**
 * Module Event Sourcing Command
 * 
 * Demonstrates Event Sourcing for module lifecycle management
 * 
 * Usage:
 * ```
 * # Install module
 * php cli admin:module-event-sourcing install --id=cms --name=CMS --version=1.0.0
 * 
 * # Enable module
 * php cli admin:module-event-sourcing enable --id=cms
 * 
 * # Show module state
 * php cli admin:module-event-sourcing show --id=cms
 * ```
 */
class ModuleEventSourcingCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'admin:module-event-sourcing 
                {action : Action (install|enable|disable|update|uninstall|show)}
                {--id= : Module ID}
                {--name= : Module name}
                {--version= : Module version}
                {--new-version= : New version (for update)}
                {--reason= : Reason for disable/uninstall}';
    }

    public function description(): string
    {
        return 'Demonstrate Event Sourcing with Module aggregate';
    }

    public function handle(): int
    {
        /** @var ModuleAggregateService $service */
        $service = $this->app->make(ModuleAggregateService::class);

        $action = $this->argument('action');

        try {
            switch ($action) {
                case 'install':
                    return $this->installModule($service);

                case 'enable':
                    return $this->enableModule($service);

                case 'disable':
                    return $this->disableModule($service);

                case 'update':
                    return $this->updateModule($service);

                case 'uninstall':
                    return $this->uninstallModule($service);

                case 'show':
                    return $this->showModule($service);

                default:
                    $this->io->error("Unknown action: {$action}");
                    return self::FAILURE;
            }
        } catch (\DomainException $e) {
            $this->io->error("Domain Error: {$e->getMessage()}");
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->io->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function installModule(ModuleAggregateService $service): int
    {
        $id = $this->option('id') ?? $this->io->ask('Module ID:', 'test-module');
        $name = $this->option('name') ?? $this->io->ask('Module Name:', 'Test Module');
        $version = $this->option('version') ?? $this->io->ask('Version:', '1.0.0');

        $this->io->writeln('<info>Installing module via Event Sourcing...</info>');

        $service->installModule(
            $id,
            $name,
            $version,
            [], // dependencies
            ['description' => 'Installed via Event Sourcing']
        );

        $this->io->success('Module installed!');
        $this->io->writeln("<comment>Module ID: {$id}</comment>");
        $this->io->writeln("<comment>Next: php cli admin:module-event-sourcing enable --id={$id}</comment>");

        return self::SUCCESS;
    }

    private function enableModule(ModuleAggregateService $service): int
    {
        $id = $this->getRequiredOption('id');

        $this->io->writeln('<info>Enabling module via Event Sourcing...</info>');

        $service->enableModule($id);

        $this->io->success('Module enabled!');
        $this->showModule($service);

        return self::SUCCESS;
    }

    private function disableModule(ModuleAggregateService $service): int
    {
        $id = $this->getRequiredOption('id');
        $reason = $this->option('reason') ?? $this->io->ask('Reason:', 'Manual disable');

        $this->io->writeln('<info>Disabling module via Event Sourcing...</info>');

        $service->disableModule($id, $reason);

        $this->io->success('Module disabled!');
        $this->showModule($service);

        return self::SUCCESS;
    }

    private function updateModule(ModuleAggregateService $service): int
    {
        $id = $this->getRequiredOption('id');
        $newVersion = $this->option('new-version') ?? $this->io->ask('New Version:');

        $this->io->writeln('<info>Updating module via Event Sourcing...</info>');

        $service->updateModule(
            $id,
            $newVersion,
            [],
            ['Updated via CLI']
        );

        $this->io->success('Module updated!');
        $this->showModule($service);

        return self::SUCCESS;
    }

    private function uninstallModule(ModuleAggregateService $service): int
    {
        $id = $this->getRequiredOption('id');
        $reason = $this->option('reason') ?? $this->io->ask('Reason:', 'No longer needed');

        $this->io->writeln('<info>Uninstalling module via Event Sourcing...</info>');

        $service->uninstallModule($id, $reason);

        $this->io->success('Module uninstalled!');
        $this->showModule($service);

        return self::SUCCESS;
    }

    private function showModule(ModuleAggregateService $service): int
    {
        $id = $this->getRequiredOption('id');

        $state = $service->getModuleState($id);

        if (!$state) {
            $this->io->warning("Module {$id} not found in event store");
            return self::FAILURE;
        }

        $this->io->writeln('<info>Module State (reconstituted from events):</info>');
        
        $tableData = [
            ['Module ID', $state['id']],
            ['Name', $state['name']],
            ['Version', $state['version']],
            ['Status', $state['status']],
            ['Is Installed', $state['is_installed'] ? 'Yes' : 'No'],
            ['Is Enabled', $state['is_enabled'] ? 'Yes' : 'No'],
        ];

        if ($state['installed_at']) {
            $tableData[] = ['Installed At', $state['installed_at']];
        }

        if ($state['enabled_at']) {
            $tableData[] = ['Enabled At', $state['enabled_at']];
        }

        if ($state['disabled_at']) {
            $tableData[] = ['Disabled At', $state['disabled_at']];
        }

        if ($state['uninstalled_at']) {
            $tableData[] = ['Uninstalled At', $state['uninstalled_at']];
        }

        $this->io->table(['Field', 'Value'], $tableData);

        if (!empty($state['dependencies'])) {
            $this->io->writeln('<comment>Dependencies:</comment>');
            $this->io->writeln(json_encode($state['dependencies'], JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }

    private function getRequiredOption(string $name): string
    {
        $value = $this->option($name);
        
        if (!$value) {
            throw new \RuntimeException("--{$name} is required");
        }

        return (string) $value;
    }
}

