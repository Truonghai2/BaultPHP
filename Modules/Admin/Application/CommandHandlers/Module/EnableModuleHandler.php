<?php

namespace Modules\Admin\Application\CommandHandlers\Module;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Module\ModuleManager;
use Core\Support\Facades\Audit;
use Modules\Admin\Infrastructure\Models\Module;

/**
 * EnableModuleHandler
 * 
 * Handles the EnableModuleCommand.
 */
class EnableModuleHandler implements CommandHandlerInterface
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

        if ($module->enabled) {
            return true;
        }

        $this->validateDependencies($command->moduleName);

        $this->moduleManager->enable($command->moduleName);

        Audit::log(
            'system',
            "Module '{$command->moduleName}' has been enabled",
            [
                'module' => $command->moduleName,
                'action' => 'enable'
            ],
            'info'
        );

        return true;
    }

    /**
     * Validate module dependencies.
     * 
     * @throws \Exception
     */
    private function validateDependencies(string $moduleName): void
    {
        
    }
}

