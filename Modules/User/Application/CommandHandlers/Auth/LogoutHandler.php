<?php

namespace Modules\User\Application\CommandHandlers\Auth;

use Core\CQRS\Contracts\CommandInterface;
use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\Contracts\Auth\StatefulGuard;
use Core\Support\Facades\Auth;
use Core\Support\Facades\Audit;
use Modules\User\Application\Commands\Auth\LogoutCommand;
use Modules\User\Infrastructure\Models\User;

/**
 * LogoutHandler
 * 
 * Handles user logout.
 */
class LogoutHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof LogoutCommand) {
            throw new \InvalidArgumentException('LogoutHandler can only handle LogoutCommand.');
        }

        $user = User::find($command->userId);

        if (!$user) {
            return false;
        }

        /** @var StatefulGuard $guard */
        $guard = Auth::guard('web');
        $guard->logout();

        // Audit log
        Audit::log(
            'authentication',
            "User logged out: {$user->email}",
            $user,
            null, null, null, null, null, 
            [
                'ip_address' => request()->ip() ?? 'unknown',
                'action' => 'logout'
            ],
            'info'
        );

        return true;
    }
}
