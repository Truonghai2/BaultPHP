<?php

declare(strict_types=1);

namespace Core\Contracts\Session;

/**
 * Interface SessionHandlerInterface
 *
 * Định nghĩa các phương thức cần thiết cho một trình xử lý session.
 * Tương thích với SessionHandlerInterface của PHP.
 *
 * @package Core\Contracts\Session
 */
interface SessionHandlerInterface
{
    /**
     * Mở một session.
     */
    public function open(string $path, string $name): bool;

    /**
     * Đóng session.
     */
    public function close(): bool;

    /**
     * Đọc dữ liệu session.
     */
    public function read(string $id): string|false;

    /**
     * Ghi dữ liệu session.
     */
    public function write(string $id, string $data): bool;

    /**
     * Hủy một session.
     */
    public function destroy(string $id): bool;

    /**
     * Dọn dẹp các session cũ (Garbage Collection).
     */
    public function gc(int $max_lifetime): int|false;
}
