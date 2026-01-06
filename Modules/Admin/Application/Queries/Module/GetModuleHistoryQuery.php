<?php

namespace Modules\Admin\Application\Queries\Module;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetModuleHistoryQuery
 *
 * Query to retrieve audit history for a specific module.
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
