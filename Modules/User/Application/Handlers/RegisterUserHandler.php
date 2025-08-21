<?php

namespace Modules\User\Application\Handlers;

use Core\Events\EventDispatcherInterface;
use Modules\User\Application\Commands\RegisterUserCommand;
use Modules\User\Domain\Events\UserWasCreated;
use Modules\User\Infrastructure\Models\User;

/**
 * Handles the logic for the RegisterUserCommand.
 */
class RegisterUserHandler
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    /**
     * Creates a new user, persists it, and dispatches an event.
     *
     * @return User The newly created user model.
     */
    public function handle(RegisterUserCommand $command): User
    {
        $user = User::create([
            'name' => $command->name,
            'email' => $command->email,
            'password' => password_hash($command->password, PASSWORD_DEFAULT),
        ]);

        $this->dispatcher->dispatch(new UserWasCreated($user));

        return $user;
    }
}
