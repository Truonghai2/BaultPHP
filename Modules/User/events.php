<?php

use Modules\User\Application\Listeners\FlushPermissionCacheForRoleUsers;
use Modules\User\Application\Listeners\FlushPermissionCacheOnProfileUpdate;
use Modules\User\Application\Listeners\FlushPermissionCacheOnRoleChange;
use Modules\User\Application\Listeners\FlushUserPermissionCache;
use Modules\User\Domain\Events\RoleAssignedToUser;
use Modules\User\Domain\Events\RolePermissionsChanged;
use Modules\User\Domain\Events\UserDeleted;

/**
 * Event-listener mappings for the User module.
 * This file is automatically discovered and loaded by the EventServiceProvider.
 */
return [
    /**
     * Khi một vai trò được gán cho người dùng, chỉ cần xóa cache của người đó.
     * Sử dụng listener chuyên biệt thay vì listener chung.
     */
    RoleAssignedToUser::class => [
        FlushPermissionCacheOnRoleChange::class,
    ],

    /**
     * Khi quyền của một vai trò thay đổi, phải xóa cache của TẤT CẢ người dùng có vai trò đó.
     */
    RolePermissionsChanged::class => [
        FlushPermissionCacheForRoleUsers::class,
    ],

    /**
     * Khi một người dùng bị xóa khỏi hệ thống, cache quyền của họ cũng cần được xóa.
     */
    UserDeleted::class => [
        FlushUserPermissionCache::class,
    ],

    /**
     * Khi hồ sơ người dùng được cập nhật, xóa cache để đảm bảo tính nhất quán.
     */
    \Modules\User\Domain\Events\UserProfileUpdated::class => [
        FlushPermissionCacheOnProfileUpdate::class,
    ],
];
