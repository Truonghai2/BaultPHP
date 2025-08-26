<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use Core\Support\Facades\Hash;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\UserEntity;
use Modules\User\Infrastructure\Models\User as UserModel;

class UserRepository implements UserRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity,
    ) {
        // "username" ở đây thường là email của người dùng.
        /** @var UserModel|null $user */
        $user = UserModel::where('email', '=', $username)->first();

        if (!$user) {
            return null;
        }

        // Xác thực mật khẩu.
        if (Hash::check($password, $user->password)) {
            return new UserEntity($user->id);
        }

        return null;
    }
}
