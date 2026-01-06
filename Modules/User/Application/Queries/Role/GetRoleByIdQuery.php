<?php

namespace Modules\User\Application\Queries\Role;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetRoleByIdQuery
 * 
 * Query to retrieve a role by ID.
 */
class GetRoleByIdQuery implements QueryInterface
{
    public function __construct(
        public readonly int $roleId,
        public readonly bool $withPermissions = false,
        public readonly bool $withUsers = false
    ) {}

    public function getQueryName(): string
    {
        return 'user.role.get_by_id';
    }
}

