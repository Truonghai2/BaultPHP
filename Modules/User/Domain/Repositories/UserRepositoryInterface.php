<?php

namespace Modules\User\Domain\Repositories;

use Modules\User\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function all(): iterable;
    public function create(User $user): User;
    public function update(User $user): bool;
    public function delete(int $id): bool;
    public function findById(int $id): ?User;
    public function save(User $user): bool;
}
