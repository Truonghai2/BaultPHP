<?php

namespace Modules\Admin\Application\Queries\Module;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetModuleHistoryQuery
 *
 * Query to retrieve audit history for a specific module.
 *
 * @property-read string $moduleName
 * @property-read int|null $limit
 */
class GetModuleHistoryQuery implements QueryInterface
{
    public function __construct(
        public readonly string $moduleName,
        public readonly ?int $limit = 50,
    ) {
    }

    public function getQueryName(): string
    {
        return 'admin.module.get_history';
    }
}
