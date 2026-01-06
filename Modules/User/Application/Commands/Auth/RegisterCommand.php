<?php

namespace Modules\User\Application\Commands\Auth;

use Core\CQRS\Contracts\CommandInterface;

/**
 * RegisterCommand
 *
 * Command to register a new user.
 *
 * @property-read string $name
 * @property-read string $email
 * @property-read string $password
 */
class RegisterCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.auth.register';
    }
}
