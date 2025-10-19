<?php

declare(strict_types=1);

namespace Modules\User\Application\Handlers;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Events\EventDispatcherInterface;
use Core\Support\Facades\Auth;
use Modules\User\Application\Commands\DeleteUserCommand;
use Modules\User\Domain\Events\UserDeleted;
use Modules\User\Domain\Exceptions\UserNotFoundException;
use Modules\User\Infrastructure\Models\User;

class DeleteUserHandler implements CommandHandler
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @param Command|DeleteUserCommand $command
     * @return void
     * @throws UserNotFoundException
     * @throws \App\Exceptions\AuthorizationException
     */
    public function handle(Command $command): void
    {
        /** @var User|null $userToDelete */
        $userToDelete = User::find($command->userId);

        if (!$userToDelete) {
            throw new UserNotFoundException("User with ID {$command->userId} not found.");
        }

        /** @var User|null $currentUser */
        $currentUser = Auth::user();

        if (!$currentUser || !$currentUser->can('delete', $userToDelete)) {
            throw new \App\Exceptions\AuthorizationException('You are not authorized to delete this user.');
        }

        $userToDelete->delete();

        $this->dispatcher->dispatch(new UserDeleted($userToDelete->id));
    }
}
