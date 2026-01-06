<?php

namespace Modules\Admin\Application\CommandHandlers\Module;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\Admin\Infrastructure\Models\Module;

/**
 * InstallModuleHandler
 * 
 * Handles the InstallModuleCommand.
 */
class InstallModuleHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): int
    {
        $existing = Module::where('name', '=', $command->moduleName)->first();
        
        if ($existing) {
            throw new \Exception("Module '{$command->moduleName}' is already installed");
        }

        $modulePath = base_path("Modules/{$command->moduleName}");
        if (!is_dir($modulePath)) {
            throw new \Exception("Module directory not found: {$modulePath}");
        }

        $moduleJsonPath = "{$modulePath}/module.json";
        if (!file_exists($moduleJsonPath)) {
            throw new \Exception("module.json not found in {$modulePath}");
        }

        $module = Module::create([
            'name' => $command->moduleName,
            'version' => $command->version,
            'description' => $command->description,
            'enabled' => false,
            'status' => 'installed'
        ]);

        Audit::log(
            'system',
            "Module '{$command->moduleName}' installed successfully",
            [
                'module' => $command->moduleName,
                'version' => $command->version,
                'dependencies' => $command->dependencies,
                'action' => 'install'
            ],
            'info'
        );

        return $module->id;
    }
}

