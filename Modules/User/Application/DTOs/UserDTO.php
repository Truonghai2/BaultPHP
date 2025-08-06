<?php

declare(strict_types=1);

namespace Modules\User\Application\DTOs;

/**
 * Đây là một Data Transfer Object (DTO) đơn giản.
 * Nó không có hành vi, chỉ chứa dữ liệu đã được tối ưu cho việc đọc và gửi về frontend.
 */
class UserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $createdAt,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: $data['name'],
            email: $data['email'],
            createdAt: $data['created_at'],
        );
    }
}
