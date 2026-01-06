<?php

namespace Modules\User\Application\Queries\Role;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetRolePermissionsQuery
 *
 * Query to retrieve all permissions for a role.
 */
class GetRolePermissionsQuery implements QueryInterface
{
    public function __construct(
        public readonly int $roleId,
    ) {
    }

    public function getQueryName(): string
    {
        return 'user.role.get_permissions';
    }
}
