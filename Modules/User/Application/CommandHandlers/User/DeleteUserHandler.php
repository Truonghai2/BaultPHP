<?php

namespace Modules\User\Application\CommandHandlers\User;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Application\Commands\User\DeleteUserCommand;
use Modules\User\Infrastructure\Models\User;

/**
 * DeleteUserHandler
 *
 * Handles the DeleteUserCommand.
 */
class DeleteUserHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        if (!$command instanceof DeleteUserCommand) {
            throw new \InvalidArgumentException('DeleteUserHandler can only handle DeleteUserCommand.');
        }

        $user = User::find($command->userId);

        if (!$user) {
            throw new \Exception("User with ID {$command->userId} not found");
        }

        $email = $user->email;

        $user->delete();

        Audit::log(
            'user_action',
            "User deleted: {$email}",
            [
                'user_id' => $command->userId,
                'action' => 'user_deleted',
            ],
            'warning',
        );

        return true;
    }
}
