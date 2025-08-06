<?php

namespace Modules\User\Infrastructure\Repositories;

use App\Exceptions\NotFoundException;
use Modules\User\Domain\Entities\User as UserEntity;
use Modules\User\Domain\Repositories\UserRepositoryInterface;
use Modules\User\Infrastructure\Mappers\UserMapper;
use Modules\User\Infrastructure\Models\User as UserModel;

class EloquentUserRepository implements UserRepositoryInterface
{
    protected UserModel $model;

    public function __construct(UserModel $model)
    {
        $this->model = $model;
    }

    public function all(): iterable
    {
        $models = $this->model->newQuery()->get();
        $entities = [];
        foreach ($models as $model) {
            $entities[] = UserMapper::toEntity($model);
        }
        return $entities;
    }

    public function find(int $id): ?UserEntity
    {
        /** @var UserModel|null $model */
        $model = $this->model->find($id);
        return $model ? UserMapper::toEntity($model) : null;
    }

    public function create(UserEntity $userEntity): UserEntity
    {
        $data = UserMapper::toModelData($userEntity);
        $createdModel = $this->model->create($data);

        // Return a new entity with the ID from the database
        return UserMapper::toEntity($createdModel);
    }

    public function update(UserEntity $userEntity): bool
    {
        $model = $this->findOrFail($userEntity->getId());
        $data = UserMapper::toModelData($userEntity);

        // Filter out null password to avoid overwriting it
        $updateData = array_filter($data, fn ($value) => !is_null($value));

        $model->fill($updateData);
        return $model->save();
    }

    public function delete(int $id): bool
    {
        $model = $this->findOrFail($id);
        return $model->delete();
    }

    protected function findOrFail(int $id): UserModel
    {
        /** @var UserModel|null $user */
        $user = $this->model->find($id);
        if (!$user) {
            throw new NotFoundException("User with ID {$id} not found.");
        }
        return $user;
    }

    public function findById(int $id): ?UserEntity
    {
        /** @var UserModel|null $model */
        $model = $this->model->find($id);
        return $model ? UserMapper::toEntity($model) : null;
    }

    public function save(UserEntity $user): bool
    {
        // If the entity has an ID, it's an update operation.
        if ($user->getId()) {
            return $this->update($user);
        }

        // Otherwise, it's a create operation.
        // The `create` method returns the new entity, so we can check its
        // existence to determine success.
        $createdEntity = $this->create($user);

        return $createdEntity instanceof UserEntity;
    }
}
