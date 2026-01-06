<?php

namespace Modules\User\Application\QueryHandlers\Permission;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\Permission\GetPermissionByIdQuery;
use Modules\User\Infrastructure\Models\Permission;

/**
 * GetPermissionByIdHandler
 *
 * Handles GetPermissionByIdQuery.
 */
class GetPermissionByIdHandler implements QueryHandlerInterface
{
    public function handle(GetPermissionByIdQuery $query): ?array
    {
        $permission = Permission::find($query->permissionId);

        if (!$permission) {
            return null;
        }

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'description' => $permission->description,
            'captype' => $permission->captype,
            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at,
        ];
    }
}
