<?php

namespace Modules\User\Infrastructure\Mappers;

use Modules\User\Domain\Entities\User as UserEntity;
use Modules\User\Infrastructure\Models\User as UserModel;

class UserMapper
{
    /**
     * Maps an ORM Model to a Domain Entity.
     */
    public static function toEntity(UserModel $model): UserEntity
    {
        return new UserEntity(
            $model->id,
            $model->name,
            $model->email,
        );
    }

    /**
     * Maps a Domain Entity to an array of data suitable for the ORM Model.
     */
    public static function toModelData(UserEntity $entity): array
    {
        return [
            'name' => $entity->getName(),
            'email' => $entity->getEmail(),
            'password' => $entity->getPassword(),
        ];
    }
}
