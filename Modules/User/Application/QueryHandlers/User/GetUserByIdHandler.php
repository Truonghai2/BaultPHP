<?php

namespace Modules\User\Application\QueryHandlers\User;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\User\GetUserByIdQuery;
use Modules\User\Infrastructure\Models\User;

/**
 * GetUserByIdHandler
 * 
 * Handles GetUserByIdQuery.
 */
class GetUserByIdHandler implements QueryHandlerInterface
{
    public function handle(GetUserByIdQuery $query): ?array
    {
        $userQuery = User::where('id', '=', $query->userId);

        if ($query->withRoles) {
            // Load roles relationship
            $user = $userQuery->first();
            if ($user) {
                $roles = $user->roles()->get();
                $userData = $user->getAttributes();
                $userData['roles'] = $roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description
                ])->toArray();
                return $userData;
            }
            return null;
        }

        $user = $userQuery->first();
        return $user ? $user->getAttributes() : null;
    }
}

