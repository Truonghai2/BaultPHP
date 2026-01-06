<?php

namespace Modules\User\Application\Commands\Auth;

use Core\CQRS\Contracts\CommandInterface;

/**
 * LoginCommand
 *
 * Command to authenticate a user.
 */
class LoginCommand implements CommandInterface
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $remember = false,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.auth.login';
    }
}
