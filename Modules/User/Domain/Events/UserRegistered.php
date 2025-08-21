<?php

namespace Modules\User\Domain\Events;

use Modules\User\Infrastructure\Models\User;

/**
 * Event được bắn ra khi một người dùng đăng ký tài khoản thông thường.
 * Có thể dùng để phân biệt với việc admin tạo user hoặc thiết lập ban đầu.
 */
class UserRegistered
{
    public function __construct(public readonly User $user)
    {
    }
}
