<?php

namespace Modules\Admin\Application\CommandHandlers\Module;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Module\ModuleManager;
use Core\Support\Facades\Audit;
use Modules\Admin\Infrastructure\Models\Module;

/**
 * DisableModuleHandler
 * 
 * Handles the DisableModuleCommand.
 */
class DisableModuleHandler implements CommandHandlerInterface
{
    public function __construct(
        private ModuleManager $moduleManager
    ) {}

    public function handle(CommandInterface $command): bool
    {
        $module = Module::where('name', '=', $command->moduleName)->first();
        
        if (!$module) {
            throw new \Exception("Module '{$command->moduleName}' not found");
        }

        if (!$module->enabled) {
            return true;
        }

        $this->checkDependents($command->moduleName);

        $this->moduleManager->disable($command->moduleName);

        Audit::log(
            'system',
            "Module '{$command->moduleName}' has been disabled",
            [
                'module' => $command->moduleName,
                'action' => 'disable'
            ],
            'warning'
        );

        return true;
    }

    /**
     * Check if other enabled modules depend on this module.
     * 
     * @throws \Exception
     */
    private function checkDependents(string $moduleName): void
    {
        
    }
}

