<?php

namespace Modules\User\Application\QueryHandlers\User;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\User\GetUserByEmailQuery;
use Modules\User\Infrastructure\Models\User;

/**
 * GetUserByEmailHandler
 *
 * Handles GetUserByEmailQuery.
 */
class GetUserByEmailHandler implements QueryHandlerInterface
{
    public function handle(GetUserByEmailQuery $query): ?array
    {
        $userQuery = User::where('email', '=', $query->email);

        if ($query->withRoles) {
            $user = $userQuery->first();
            if ($user) {
                $roles = $user->roles()->get();
                $userData = $user->getAttributes();
                $userData['roles'] = $roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                ])->toArray();
                return $userData;
            }
            return null;
        }

        $user = $userQuery->first();
        return $user ? $user->getAttributes() : null;
    }
}
