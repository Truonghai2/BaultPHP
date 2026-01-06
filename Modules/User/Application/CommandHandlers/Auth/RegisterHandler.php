<?php

namespace Modules\User\Application\CommandHandlers\Auth;

use Core\CQRS\Contracts\CommandInterface;
use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\Support\Facades\Audit;
use Core\Support\Facades\Hash;
use Modules\User\Application\Commands\Auth\RegisterCommand;
use Modules\User\Domain\Events\UserRegistered;
use Modules\User\Infrastructure\Models\User;

/**
 * RegisterHandler
 * 
 * Handles new user registration.
 */
class RegisterHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof RegisterCommand) {
            throw new \InvalidArgumentException('RegisterHandler can only handle RegisterCommand.');
        }

        // Check if email already exists
        if (User::where('email', '=', $command->email)->exists()) {
            throw new \Exception("Email {$command->email} is already registered");
        }

        // Create user
        $user = User::create([
            'name' => $command->name,
            'email' => $command->email,
            'password' => Hash::make($command->password),
            'status' => 'active'
        ]);

        // Dispatch domain event
        event(new UserRegistered($user->id, $user->email, $user->name));

        // Audit log (creation is auto-logged by Auditable trait)
        Audit::log(
            'authentication',
            "New user registered: {$user->email}",
            $user,
            [
                'ip_address' => request()->ip() ?? 'unknown',
                'action' => 'user_registered'
            ],
            'info'
        );

        return $user;
    }
}
