<?php

namespace Modules\User\Application\Commands\User;

use Core\CQRS\Contracts\CommandInterface;

/**
 * DeleteUserCommand
 *
 * Command to delete a user from the system.
 *
 * @property-read int $userId
 */
class DeleteUserCommand implements CommandInterface
{
    public function __construct(
        public readonly int $userId,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.user.delete';
    }
}
