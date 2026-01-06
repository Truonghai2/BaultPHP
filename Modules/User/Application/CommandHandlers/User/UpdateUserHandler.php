<?php

namespace Modules\User\Application\CommandHandlers\User;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Core\Support\Facades\Hash;
use Modules\User\Application\Commands\User\UpdateUserCommand;
use Modules\User\Infrastructure\Models\User;

/**
 * UpdateUserHandler
 *
 * Handles the UpdateUserCommand.
 */
class UpdateUserHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof UpdateUserCommand) {
            throw new \InvalidArgumentException('UpdateUserHandler can only handle UpdateUserCommand.');
        }

        $user = User::find($command->userId);

        if (!$user) {
            throw new \Exception("User with ID {$command->userId} not found");
        }

        if ($command->name !== null) {
            $user->name = $command->name;
        }

        if ($command->email !== null) {
            $existing = User::where('email', '=', $command->email)
                ->where('id', '!=', $command->userId)
                ->exists();

            if ($existing) {
                throw new \Exception("Email '{$command->email}' is already in use");
            }

            $user->email = $command->email;
        }

        if ($command->password !== null) {
            $user->password = Hash::make($command->password);

            Audit::security(
                "Password changed for user {$user->email}",
                ['user_id' => $user->id],
            );
        }

        $user->save();

        Audit::log(
            'user_action',
            "User updated: {$user->email}",
            [
                'user_id' => $user->id,
                'action' => 'user_updated',
            ],
            'info',
        );

        return true;
    }
}
