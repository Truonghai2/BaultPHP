<?php

namespace Modules\User\Application\Queries\Role;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetRolesQuery
 * 
 * Query to retrieve all roles.
 */
class GetRolesQuery implements QueryInterface
{
    public function __construct(
        public readonly bool $withPermissions = false,
        public readonly ?int $limit = null,
        public readonly ?int $offset = null
    ) {}

    public function getQueryName(): string
    {
        return 'user.role.get_all';
    }
}

