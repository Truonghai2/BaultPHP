<?php

namespace Modules\User\Application\Commands\Auth;

use Core\CQRS\Contracts\CommandInterface;

/**
 * LogoutCommand
 *
 * Command to logout a user.
 *
 * @property-read int $userId
 */
class LogoutCommand implements CommandInterface
{
    public function __construct(
        public readonly int $userId,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.auth.logout';
    }
}
