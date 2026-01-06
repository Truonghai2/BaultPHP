<?php

namespace Modules\User\Application\Queries\Permission;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetPermissionsQuery
 *
 * Query to retrieve all permissions.
 */
class GetPermissionsQuery implements QueryInterface
{
    public function __construct(
        public readonly ?string $captype = null,
        public readonly ?int $limit = null,
        public readonly ?int $offset = null,
    ) {
    }

    public function getQueryName(): string
    {
        return 'user.permission.get_all';
    }
}
