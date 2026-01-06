<?php

declare(strict_types=1);

namespace Modules\User\Http\Controllers\Admin;

use Core\CQRS\Command\CommandBus;
use Core\CQRS\Query\QueryBus;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Modules\User\Application\Commands\Role\AssignPermissionsCommand;
use Modules\User\Application\Commands\Role\CreateRoleCommand;
use Modules\User\Application\Commands\Role\DeleteRoleCommand;
use Modules\User\Application\Commands\Role\UpdateRoleCommand;
use Modules\User\Application\Queries\Permission\GetPermissionsQuery;
use Modules\User\Application\Queries\Role\GetRoleByIdQuery;
use Modules\User\Application\Queries\Role\GetRolesQuery;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Role Management Controller
 *
 * Admin API for managing roles and their permissions
 */
#[Route(prefix: '/admin/roles', middleware: ['auth'], group: 'web')]
class RoleController extends Controller
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    /**
     * Show roles management UI
     * GET /admin/roles
     */
    #[Route('', method: 'GET', name: 'admin.roles.index')]
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        if (!config('app.debug') && !$user->can('user.roles.view')) {
            return response('Forbidden', 403);
        }

        return response(view('admin.roles.index'));
    }

    /**
     * List all roles (API)
     * GET /admin/roles/api
     */
    #[Route('/api', method: 'GET', name: 'admin.roles.api')]
    public function listRoles(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('user.roles.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $queryParams = $request->getQueryParams();
        $query = new GetRolesQuery(
            withPermissions: isset($queryParams['with_permissions']) ? (bool)$queryParams['with_permissions'] : false,
            limit: isset($queryParams['limit']) ? (int)$queryParams['limit'] : null,
            offset: isset($queryParams['offset']) ? (int)$queryParams['offset'] : null,
        );

        $roles = $this->queryBus->dispatch($query);

        return response()->json([
            'roles' => $roles,
            'total' => count($roles),
        ]);
    }

    /**
     * Get a specific role (API)
     * GET /admin/roles/api/{id}
     */
    #[Route('/api/{id}', method: 'GET', name: 'admin.roles.show')]
    public function show(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('user.roles.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $queryParams = $request->getQueryParams();
        $query = new GetRoleByIdQuery(
            roleId: $id,
            withPermissions: isset($queryParams['with_permissions']) ? (bool)$queryParams['with_permissions'] : true,
            withUsers: isset($queryParams['with_users']) ? (bool)$queryParams['with_users'] : false,
        );

        $role = $this->queryBus->dispatch($query);

        if (!$role) {
            return response()->json(['error' => 'Role not found'], 404);
        }

        return response()->json($role);
    }

    /**
     * Create a new role (API)
     * POST /admin/roles/api
     */
    #[Route('/api', method: 'POST', name: 'admin.roles.store')]
    public function store(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('user.roles.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->getParsedBody() ?? [];

        if (!isset($data['name'])) {
            return response()->json(['error' => 'Name is required'], 400);
        }

        $command = new CreateRoleCommand(
            name: (string)$data['name'],
            description: isset($data['description']) ? (string)$data['description'] : '',
            permissionIds: isset($data['permission_ids']) && is_array($data['permission_ids']) ? array_map('intval', $data['permission_ids']) : [],
        );

        try {
            $roleId = $this->commandBus->dispatch($command);

            return response()->json([
                'message' => 'Role created successfully',
                'role_id' => $roleId,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update a role (API)
     * PUT /admin/roles/api/{id}
     */
    #[Route('/api/{id}', method: 'PUT', name: 'admin.roles.update')]
    public function update(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('user.roles.update')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->getParsedBody() ?? [];

        $command = new UpdateRoleCommand(
            roleId: $id,
            name: isset($data['name']) ? (string)$data['name'] : null,
            description: isset($data['description']) ? (string)$data['description'] : null,
        );

        try {
            $this->commandBus->dispatch($command);

            return response()->json([
                'message' => 'Role updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Delete a role (API)
     * DELETE /admin/roles/api/{id}
     */
    #[Route('/api/{id}', method: 'DELETE', name: 'admin.roles.destroy')]
    public function destroy(int $id): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('user.roles.delete')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $command = new DeleteRoleCommand(roleId: $id);

        try {
            $this->commandBus->dispatch($command);

            return response()->json([
                'message' => 'Role deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Assign permissions to a role (API)
     * POST /admin/roles/api/{id}/permissions
     */
    #[Route('/api/{id}/permissions', method: 'POST', name: 'admin.roles.assign_permissions')]
    public function assignPermissions(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('user.roles.update')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->getParsedBody() ?? [];

        if (!isset($data['permission_ids']) || !is_array($data['permission_ids'])) {
            return response()->json(['error' => 'permission_ids is required and must be an array'], 400);
        }

        $command = new AssignPermissionsCommand(
            roleId: $id,
            permissionIds: array_map('intval', $data['permission_ids']),
        );

        try {
            $this->commandBus->dispatch($command);

            return response()->json([
                'message' => 'Permissions assigned successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get all permissions (for role assignment form)
     * GET /admin/roles/api/permissions
     */
    #[Route('/api/permissions', method: 'GET', name: 'admin.roles.permissions')]
    public function getPermissions(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('user.roles.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $queryParams = $request->getQueryParams();
        $query = new GetPermissionsQuery(
            captype: isset($queryParams['captype']) ? (string)$queryParams['captype'] : null,
            limit: null,
            offset: null,
        );

        $permissions = $this->queryBus->dispatch($query);

        return response()->json([
            'permissions' => $permissions,
        ]);
    }
}

