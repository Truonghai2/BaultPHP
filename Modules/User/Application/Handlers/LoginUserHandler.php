<?php

declare(strict_types=1);

namespace Modules\User\Application\Handlers;

use Core\Contracts\Auth\StatefulGuard;
use Core\Support\Facades\Auth;
use Modules\User\Application\Commands\LoginUserCommand;
use Modules\User\Infrastructure\Models\User;

class LoginUserHandler
{
    public function __construct(
    ) {
    }

    /**
     * @return User|null The authenticated user on success, null on failure.
     */
    public function handle(LoginUserCommand $command): ?User
    {
        $credentials = [
            'email' => $command->email,
            'password' => $command->password,
        ];

        /** @var StatefulGuard $guard */
        $guard = Auth::guard('web');

        return $guard->attempt($credentials, $command->remember) ? $guard->user() : null;
    }
}
