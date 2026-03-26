<?php

namespace Modules\User\Application\CommandHandlers\Auth;

use Core\Contracts\Auth\StatefulGuard;
use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Core\Support\Facades\Auth;
use Modules\User\Application\Commands\Auth\LoginCommand;
use Modules\User\Infrastructure\Models\User;

/**
 * LoginHandler
 *
 * Handles user authentication.
 */
class LoginHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): ?User
    {
        if (!$command instanceof LoginCommand) {
            throw new \InvalidArgumentException('LoginHandler can only handle LoginCommand.');
        }

        $credentials = [
            'email' => $command->email,
            'password' => $command->password,
        ];

        /** @var StatefulGuard $guard */
        $guard = Auth::guard('web');

        $success = $guard->attempt($credentials, $command->remember);

        if ($success) {
            /** @var User|null $user */
            $user = $guard->user();

            // Audit log successful login
            Audit::auth(
                'login_success',
                $user,
                [
                    'email' => $user->email,
                    'remember' => $command->remember,
                    'ip_address' => request()->ip() ?? 'unknown',
                    'user_agent' => request()->header('User-Agent') ?? 'unknown',
                ],
                'info',
            );

            return $user;
        }

        // Audit log failed login
        Audit::auth(
            'login_failed',
            null,
            [
                'email' => $command->email,
                'ip_address' => request()->ip() ?? 'unknown',
                'user_agent' => request()->header('User-Agent') ?? 'unknown',
            ],
            'warning',
        );

        return null;
    }
}
