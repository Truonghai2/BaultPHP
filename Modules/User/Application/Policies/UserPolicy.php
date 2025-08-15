<?php

declare(strict_types=1);

namespace Modules\User\Application\Policies;

use Core\Auth\Access\Response;
use Modules\User\Infrastructure\Models\User;

class UserPolicy
{
    /**
     * Xác định xem người dùng hiện tại có thể xóa người dùng mục tiêu hay không.
     */
    public function delete(User $currentUser, User $userToDelete): Response
    {
        if ($currentUser->id === $userToDelete->id) {
            return Response::deny('You cannot delete your own account.');
        }

        return $currentUser->can('users:delete')
            ? Response::allow()
            : Response::deny('You do not have permission to delete users.');
    }
}
