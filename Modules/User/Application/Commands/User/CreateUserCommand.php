<?php

namespace Modules\User\Application\Commands\User;

use Core\CQRS\Contracts\CommandInterface;

/**
 * CreateUserCommand
 *
 * Command to create a new user in the system.
 *
 * @property-read string $name
 * @property-read string $email
 * @property-read string $password
 */
class CreateUserCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.user.create';
    }
}
