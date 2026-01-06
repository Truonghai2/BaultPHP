<?php

namespace Modules\User\Application\QueryHandlers\Role;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\Role\GetRolePermissionsQuery;
use Modules\User\Infrastructure\Models\Role;

/**
 * GetRolePermissionsHandler
 *
 * Handles GetRolePermissionsQuery.
 */
class GetRolePermissionsHandler implements QueryHandlerInterface
{
    public function handle(GetRolePermissionsQuery $query): array
    {
        $role = Role::find($query->roleId);

        if (!$role) {
            throw new \Exception("Role with ID {$query->roleId} not found");
        }

        $permissions = $role->permissions()->get();

        return $permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->description,
                'captype' => $permission->captype,
                'created_at' => $permission->created_at,
            ];
        })->toArray();
    }
}
