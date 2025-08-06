<?php

declare(strict_types=1);

namespace Core\Events;

interface EventDispatcherInterface
{
    /**
     * Đăng ký một listener cho một event cụ thể.
     */
    public function listen(string $event, string|callable $listener): void;

    /**
     * Bắn ra một event để tất cả các listener đã đăng ký có thể xử lý.
     */
    public function dispatch(object $event): void;
}
