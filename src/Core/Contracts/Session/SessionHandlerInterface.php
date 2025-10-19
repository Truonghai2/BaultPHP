<?php

namespace Core\Contracts\Session;

/**
 * Interface cho các trình xử lý lưu trữ session.
 * Dựa trên SessionHandlerInterface của PHP.
 */
interface SessionHandlerInterface
{
    /**
     * Mở một session.
     */
    public function open(string $savePath, string $sessionName): bool;

    /**
     * Đóng session.
     */
    public function close(): bool;

    /**
     * Đọc dữ liệu session.
     */
    public function read(string $sessionId): string|false;

    /**
     * Ghi dữ liệu session.
     */
    public function write(string $sessionId, string $data): bool;

    /**
     * Hủy một session.
     */
    public function destroy(string $sessionId): bool;

    /**
     * Dọn dẹp các session cũ (Garbage Collection).
     */
    public function gc(int $maxLifetime): int|false;
}
