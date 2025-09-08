<?php

declare(strict_types=1);

namespace Modules\User\Application\Handlers;

use Core\Auth\AuthManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LogoutUserHandler
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly SessionInterface $session,
    ) {
    }

    public function handle(): void
    {
        $this->auth->guard('web')->logout();
    }
}
