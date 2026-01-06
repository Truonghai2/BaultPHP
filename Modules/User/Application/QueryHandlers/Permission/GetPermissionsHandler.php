<?php

namespace Modules\User\Application\QueryHandlers\Permission;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\Permission\GetPermissionsQuery;
use Modules\User\Infrastructure\Models\Permission;

/**
 * GetPermissionsHandler
 *
 * Handles GetPermissionsQuery.
 */
class GetPermissionsHandler implements QueryHandlerInterface
{
    public function handle(GetPermissionsQuery $query): array
    {
        $permissionQuery = Permission::query();

        // Apply captype filter
        if ($query->captype) {
            $permissionQuery->where('captype', '=', $query->captype);
        }

        // Apply pagination
        if ($query->limit) {
            $permissionQuery->limit($query->limit);
        }

        if ($query->offset) {
            $permissionQuery->offset($query->offset);
        }

        $permissions = $permissionQuery->orderBy('name', 'asc')->get();

        return $permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->description,
                'captype' => $permission->captype,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ];
        })->toArray();
    }
}
