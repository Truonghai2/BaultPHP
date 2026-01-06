<?php

namespace Modules\User\Application\Queries\User;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetUserRolesQuery
 *
 * Query to retrieve all roles assigned to a user.
 *
 * @property-read int $userId
 * @property-read int|null $contextId
 */
class GetUserRolesQuery implements QueryInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $contextId = null,
    ) {
    }

    public function getQueryName(): string
    {
        return 'user.user.get_roles';
    }
}
