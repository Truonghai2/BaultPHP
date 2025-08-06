<?php

declare(strict_types=1);

namespace Modules\User\Application;

use Modules\User\Infrastructure\Models\User;

class UserFinder
{
    public function findById(int $id): ?UserDto
    {
        $user = User::find($id);

        if (!$user) {
            return null;
        }

        return new UserDto(
            $user->id,
            $user->name,
            $user->email,
        );
    }
}
