<?php

namespace Core\Contracts\Session;

interface SessionInterface
{
    /**
     * Bắt đầu session mới hoặc tiếp tục session cũ.
     */
    public function start(): bool;

    /**
     * Lưu dữ liệu session.
     */
    public function save(): void;

    /**
     * Lấy tất cả dữ liệu session.
     */
    public function all(): array;

    /**
     * Kiểm tra xem một key có tồn tại trong session không.
     */
    public function has(string $key): bool;

    /**
     * Lấy một giá trị từ session.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Gán một giá trị vào session.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Xóa một giá trị khỏi session.
     */
    public function remove(string $key): void;

    /**
     * Lấy và xóa một giá trị khỏi session.
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Xóa toàn bộ dữ liệu session.
     */
    public function flush(): void;

    /**
     * Lấy ID của session.
     */
    public function getId(): string;

    /**
     * Gán ID cho session.
     */
    public function setId(string $id): void;

    /**
     * Tạo lại ID của session.
     */
    public function regenerate(bool $destroy = false): bool;

    /**
     * Vô hiệu hóa session (xóa dữ liệu và tạo lại ID).
     */
    public function invalidate(): bool;

    /**
     * Lấy tên của session.
     */
    public function getName(): string;

    /**
     * Kiểm tra xem session đã được bắt đầu chưa.
     */
    public function isStarted(): bool;

    /**
     * Lấy Flash Bag.
     */
    public function getFlashBag();
}
