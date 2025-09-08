<?php

declare(strict_types=1);

namespace Modules\User\Domain\Events;

/**
 * Event được kích hoạt sau khi một người dùng đã bị xóa khỏi hệ thống.
 */
class UserDeleted
{
    public function __construct(public readonly int $deletedUserId)
    {
    }
}
