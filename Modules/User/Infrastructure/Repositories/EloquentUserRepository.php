<?php

namespace Modules\User\Infrastructure\Repositories;

use Modules\User\Domain\Repositories\UserRepositoryInterface;
use Modules\User\Infrastructure\Models\User;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function all(): iterable
    {
        return User::all();
    }

    public function create(array $data): mixed
    {
        return User::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $user = User::findOrFail($id);
        return $user->update($data);
    }

    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }
}
