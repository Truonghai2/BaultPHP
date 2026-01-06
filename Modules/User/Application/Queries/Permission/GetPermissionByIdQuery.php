<?php

namespace Modules\User\Application\Queries\Permission;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetPermissionByIdQuery
 *
 * Query to retrieve a permission by ID.
 */
class GetPermissionByIdQuery implements QueryInterface
{
    public function __construct(
        public readonly int $permissionId,
    ) {
    }

    public function getQueryName(): string
    {
        return 'user.permission.get_by_id';
    }
}
