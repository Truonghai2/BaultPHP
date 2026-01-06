<?php

namespace Modules\Admin\Application\Commands\Module;

use Core\CQRS\Contracts\CommandInterface;

/**
 * EnableModuleCommand
 * 
 * Command to enable a module in the system.
 */
class EnableModuleCommand implements CommandInterface
{
    public function __construct(
        public readonly string $moduleName
    ) {}

    public function getCommandName(): string
    {
        return 'admin.module.enable';
    }
}

