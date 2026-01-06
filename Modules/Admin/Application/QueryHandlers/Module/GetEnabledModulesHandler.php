<?php

namespace Modules\Admin\Application\QueryHandlers\Module;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\Admin\Application\Queries\Module\GetEnabledModulesQuery;
use Modules\Admin\Infrastructure\Models\Module;

/**
 * GetEnabledModulesHandler
 *
 * Handles GetEnabledModulesQuery.
 */
class GetEnabledModulesHandler implements QueryHandlerInterface
{
    public function handle(GetEnabledModulesQuery $query): array
    {
        return Module::where('enabled', '=', true)
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();
    }
}
