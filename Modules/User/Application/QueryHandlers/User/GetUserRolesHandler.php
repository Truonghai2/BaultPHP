<?php

namespace Modules\User\Application\QueryHandlers\User;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\User\Application\Queries\User\GetUserRolesQuery;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;

/**
 * GetUserRolesHandler
 *
 * Handles GetUserRolesQuery.
 */
class GetUserRolesHandler implements QueryHandlerInterface
{
    public function handle(GetUserRolesQuery $query): array
    {
        $user = User::find($query->userId);

        if (!$user) {
            throw new \Exception("User with ID {$query->userId} not found");
        }

        // Get role assignments
        $assignmentQuery = RoleAssignment::where('user_id', '=', $query->userId);

        if ($query->contextId !== null) {
            $assignmentQuery->where('context_id', '=', $query->contextId);
        }

        $assignments = $assignmentQuery->get();

        // Load roles
        return $assignments->map(function ($assignment) {
            $role = $assignment->role()->first();
            return [
                'assignment_id' => $assignment->id,
                'role_id' => $role->id,
                'role_name' => $role->name,
                'role_description' => $role->description,
                'context_id' => $assignment->context_id,
                'assigned_at' => $assignment->created_at,
            ];
        })->toArray();
    }
}
