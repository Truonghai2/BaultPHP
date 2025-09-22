<?php

declare(strict_types=1);

namespace Modules\User\Application\Queries;

use Core\ORM\QueryBuilder;
use Modules\User\Application\DTOs\UserDTO;

/**
 * Đây là service Query của chúng ta. Nó chịu trách nhiệm cho tất cả các hoạt động đọc dữ liệu User.
 * Nó trả về các DTO đơn giản, không phải các Model phức tạp.
 */
class UserFinder
{
    private QueryBuilder $db;

    public function __construct(QueryBuilder $db)
    {
        $this->db = $db->table('users');
    }

    public function findById(int $id): ?UserDTO
    {
        $user = $this->db->where('id', '=', $id)->first();

        if (!$user) {
            return null;
        }

        return UserDTO::fromArray((array)$user);
    }

    /**
     * @return UserDTO[]
     */
    public function findAll(): array
    {
        $users = $this->db->get();

        return array_map(
            fn ($user) => UserDTO::fromArray((array)$user),
            $users->all(),
        );
    }
}
