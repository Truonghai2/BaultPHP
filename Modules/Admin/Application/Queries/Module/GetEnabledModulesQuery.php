<?php

namespace Modules\Admin\Application\Queries\Module;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetEnabledModulesQuery
 *
 * Query to retrieve all enabled modules.
 *
 * @property-read array $enabledModules
 */
class GetEnabledModulesQuery implements QueryInterface
{
    // No parameters needed - get all enabled modules

    public function getQueryName(): string
    {
        return 'admin.module.get_enabled';
    }
}
