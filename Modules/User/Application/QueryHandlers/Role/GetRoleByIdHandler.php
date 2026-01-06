<?php

namespace Modules\User\Application\QueryHandlers\Role;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\Role\GetRoleByIdQuery;
use Modules\User\Infrastructure\Models\Role;

/**
 * GetRoleByIdHandler
 * 
 * Handles GetRoleByIdQuery.
 */
class GetRoleByIdHandler implements QueryHandlerInterface
{
    public function handle(GetRoleByIdQuery $query): ?array
    {
        $role = Role::find($query->roleId);

        if (!$role) {
            return null;
        }

        $roleData = [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at
        ];

        if ($query->withPermissions) {
            $permissions = $role->permissions()->get();
            $roleData['permissions'] = $permissions->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'description' => $permission->description,
                    'captype' => $permission->captype
                ];
            })->toArray();
        }

        if ($query->withUsers) {
            $users = $role->users()->get();
            $roleData['users'] = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ];
            })->toArray();
        }

        return $roleData;
    }
}

