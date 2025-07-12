<?php

namespace Modules\User\Domain\Repositories;

interface UserRepositoryInterface
{
    public function all(): iterable;

    public function create(array $data): mixed;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}