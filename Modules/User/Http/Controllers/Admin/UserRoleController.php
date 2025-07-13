<?php

namespace Modules\User\Http\Controllers\Admin;

use Core\Contracts\Events\EventDispatcherInterface;
use Http\Request;
use Http\Response;
use Modules\User\Application\Services\AccessControlService;
use Modules\User\Domain\Events\RoleAssignedToUser;
use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;

class UserRoleController
{
    public function __construct(
        private AccessControlService $acl,
        private EventDispatcherInterface $dispatcher
    ) {}

    /**
     * Gán một vai trò cho người dùng trong một context cụ thể.
     */
    public function assignRole(Request $request, User $user): Response
    {
        // Validate input
        $roleId = $request->input('role_id');
        $contextLevel = $request->input('context_level');
        $instanceId = $request->input('instance_id');

        if (!$roleId || !$contextLevel || !$instanceId) {
            return (new Response())->json(['message' => 'role_id, context_level, and instance_id are required.'], 422);
        }

        $role = Role::find($roleId);
        if (!$role) {
            return (new Response())->json(['message' => 'Role not found.'], 404);
        }

        // Tìm hoặc tạo context
        // Tạm thời, chúng ta giả định model context tồn tại.
        // Trong thực tế, bạn cần một cách để lấy model từ context_level và instance_id.
        // Ví dụ: $modelClass = 'Modules\\Post\\Infrastructure\\Models\\Post'; $model = $modelClass::find($instanceId);
        // Để đơn giản, chúng ta sẽ resolve context trực tiếp.
        $context = $this->acl->resolveContextByLevelAndId($contextLevel, $instanceId);

        // Sử dụng updateOrCreate để tránh trùng lặp và dễ dàng cập nhật
        RoleAssignment::updateOrCreate(
            [
                'user_id' => $user->id,
                'context_id' => $context->id,
            ],
            [
                'role_id' => $role->id,
            ]
        );

        // Fire an event to notify the system that a role has been assigned.
        $this->dispatcher->dispatch(new RoleAssignedToUser($user, $role));

        return (new Response())->json(['message' => "Role '{$role->name}' assigned to user '{$user->name}' in context '{$context->context_level}:{$context->instance_id}'."]);
    }
}