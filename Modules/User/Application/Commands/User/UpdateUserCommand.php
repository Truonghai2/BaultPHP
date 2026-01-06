<?php

namespace Modules\User\Application\Commands\User;

use Core\CQRS\Contracts\CommandInterface;

/**
 * UpdateUserCommand
 * 
 * Command to update user information.
 */
class UpdateUserCommand implements CommandInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null
    ) {}

    public function getCommandName(): string
    {
        return 'user.user.update';
    }
}

