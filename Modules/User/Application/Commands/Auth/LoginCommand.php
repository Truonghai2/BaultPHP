<?php

namespace Modules\User\Application\Commands\Auth;

use Core\CQRS\Contracts\CommandInterface;

/**
 * LoginCommand
 *
 * Command to authenticate a user.
 *
 * @property-read string $email
 * @property-read string $password
 * @property-read bool $remember
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
