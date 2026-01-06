<?php

namespace Modules\Admin\Application\Commands\Module;

use Core\CQRS\Contracts\CommandInterface;

/**
 * DisableModuleCommand
 *
 * Command to disable a module in the system.
 *
 * @property-read string $moduleName
 */
class DisableModuleCommand implements CommandInterface
{
    public function __construct(
        public readonly string $moduleName,
    ) {
    }

    public function getCommandName(): string
    {
        return 'admin.module.disable';
    }
}
