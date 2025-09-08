<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Modules\User\Domain\Events\RolePermissionsChanged;
use Modules\User\Domain\Services\PermissionCacheService;
use Modules\User\Infrastructure\Models\RoleAssignment;

/**
 * Xử lý sự kiện RolePermissionsChanged để xóa cache quyền của TẤT CẢ
 * người dùng được gán vai trò đó.
 * Điều này đảm bảo rằng các thay đổi về quyền của vai trò được áp dụng
 * cho tất cả người dùng liên quan.
 */
class FlushPermissionCacheForRoleUsers
{
    public function __construct(private readonly PermissionCacheService $permissionCache)
    {
    }

    public function handle(RolePermissionsChanged $event): void
    {
        // Tối ưu hóa: Thay vì lấy toàn bộ model User, chúng ta chỉ cần lấy user_id
        // từ bảng trung gian `role_assignments`.
        // Điều này tránh được việc join với bảng `users` và hydrate các model không cần thiết.
        RoleAssignment::where('role_id', $event->role->id)
            ->select('user_id')
            ->chunkById(500, function ($assignments) {
                // Lấy ra một mảng các user_id.
                $userIds = $assignments->pluck('user_id')->all();

                // Tối ưu hóa: Gọi một lệnh xóa cache duy nhất cho cả batch
                // thay vì N lệnh xóa riêng lẻ.
                if (!empty($userIds)) {
                    $this->permissionCache->flushForUserIds($userIds);
                }
            });
    }
}
