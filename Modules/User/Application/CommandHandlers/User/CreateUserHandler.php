<?php

namespace Modules\User\Application\CommandHandlers\User;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Core\Support\Facades\Hash;
use Modules\User\Application\Commands\User\CreateUserCommand;
use Modules\User\Infrastructure\Models\User;

/**
 * CreateUserHandler
 *
 * Handles the CreateUserCommand.
 */
class CreateUserHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof CreateUserCommand) {
            throw new \InvalidArgumentException('CreateUserHandler can only handle CreateUserCommand.');
        }

        // Validate email uniqueness
        if (User::where('email', '=', $command->email)->exists()) {
            throw new \Exception("Email '{$command->email}' is already in use");
        }

        // Create user
        $user = User::create([
            'name' => $command->name,
            'email' => $command->email,
            'password' => Hash::make($command->password),
        ]);

        // Additional audit log (model creation is auto-logged)
        Audit::log(
            'user_action',
            "New user created: {$command->email}",
            [
                'user_id' => $user->id,
                'email' => $command->email,
                'action' => 'user_created',
            ],
            'info',
        );

        return $user->id;
    }
}
