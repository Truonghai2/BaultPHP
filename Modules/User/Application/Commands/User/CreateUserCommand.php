<?php

namespace Modules\User\Application\Commands\User;

use Core\CQRS\Contracts\CommandInterface;

/**
 * CreateUserCommand
 * 
 * Command to create a new user in the system.
 */
class CreateUserCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
    ) {}

    public function getCommandName(): string
    {
        return 'user.user.create';
    }
}

