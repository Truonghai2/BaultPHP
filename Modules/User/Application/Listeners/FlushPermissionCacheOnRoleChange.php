<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Modules\User\Application\Jobs\FlushUserPermissionCacheJob;
use Modules\User\Domain\Events\RoleAssignedToUser;

/**
 * Xử lý sự kiện RoleAssignedToUser để xóa cache quyền của người dùng.
 * Đây là bước cực kỳ quan trọng để đảm bảo các thay đổi về vai trò
 * có hiệu lực ngay lập tức.
 */
class FlushPermissionCacheOnRoleChange
{
    public function handle(RoleAssignedToUser $event): void
    {
        // Thay vì xóa cache đồng bộ, chúng ta đẩy một job vào hàng đợi.
        // Điều này giúp phản hồi cho người dùng nhanh hơn, mặc dù trong trường hợp này
        // tác vụ xóa cache đã rất nhanh và việc này không thực sự cần thiết.
        dispatch(new FlushUserPermissionCacheJob($event->user->id));
    }
}
