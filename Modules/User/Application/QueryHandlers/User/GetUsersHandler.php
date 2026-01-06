<?php

namespace Modules\User\Application\QueryHandlers\User;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\User\GetUsersQuery;
use Modules\User\Infrastructure\Models\User;

/**
 * GetUsersHandler
 *
 * Handles GetUsersQuery.
 */
class GetUsersHandler implements QueryHandlerInterface
{
    public function handle(GetUsersQuery $query): array
    {
        $userQuery = User::query();

        // Apply search filter
        if ($query->search) {
            $userQuery->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query->search}%")
                  ->orWhere('email', 'LIKE', "%{$query->search}%");
            });
        }

        // Apply limit and offset
        if ($query->limit) {
            $userQuery->limit($query->limit);
        }

        if ($query->offset) {
            $userQuery->offset($query->offset);
        }

        $users = $userQuery->orderBy('created_at', 'desc')->get();

        // Map to array
        return $users->map(function ($user) use ($query) {
            $userData = $user->getAttributes();

            if ($query->withRoles) {
                $roles = $user->roles()->get();
                $userData['roles'] = $roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                ])->toArray();
            }

            // Remove password from response
            unset($userData['password']);
            unset($userData['remember_token']);

            return $userData;
        })->toArray();
    }
}
