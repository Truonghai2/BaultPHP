<?php

namespace Modules\User\Application\QueryHandlers\Role;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\Role\GetRolesQuery;
use Modules\User\Infrastructure\Models\Role;

/**
 * GetRolesHandler
 * 
 * Handles GetRolesQuery.
 */
class GetRolesHandler implements QueryHandlerInterface
{
    public function handle(GetRolesQuery $query): array
    {
        $roleQuery = Role::query();

        if ($query->limit) {
            $roleQuery->limit($query->limit);
        }

        if ($query->offset) {
            $roleQuery->offset($query->offset);
        }

        $roles = $roleQuery->orderBy('name', 'asc')->get();

        return $roles->map(function($role) use ($query) {
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

            return $roleData;
        })->toArray();
    }
}

