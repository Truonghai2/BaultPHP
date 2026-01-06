<?php

namespace Modules\Admin\Application\Commands\Module;

use Core\CQRS\Contracts\CommandInterface;

/**
 * UpdateModuleSettingCommand
 *
 * Command to update a module setting.
 *
 * @property-read string $moduleName
 * @property-read string $key
 * @property-read mixed $value
 * @property-read string $type
 * @property-read string|null $description
 * @property-read string|null $group
 */
class UpdateModuleSettingCommand implements CommandInterface
{
    public function __construct(
        public readonly string $moduleName,
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $type = 'string',
        public readonly ?string $description = null,
        public readonly ?string $group = null,
    ) {
    }

    public function getCommandName(): string
    {
        return 'admin.module.update_setting';
    }
}
