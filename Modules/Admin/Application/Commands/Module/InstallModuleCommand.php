<?php

namespace Modules\Admin\Application\Commands\Module;

use Core\CQRS\Contracts\CommandInterface;

/**
 * InstallModuleCommand
 *
 * Command to install a new module into the system.
 *
 * @property-read string $moduleName
 * @property-read string $version
 * @property-read string $description
 * @property-read array $dependencies
 */
class InstallModuleCommand implements CommandInterface
{
    public function __construct(
        public readonly string $moduleName,
        public readonly string $version,
        public readonly string $description = '',
        public readonly array $dependencies = [],
    ) {
    }

    public function getCommandName(): string
    {
        return 'admin.module.install';
    }
}
