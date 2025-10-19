<?php

declare(strict_types=1);

namespace Modules\User\Application\Handlers;

use Core\Auth\AuthManager;

class LogoutUserHandler
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {
    }

    public function handle(): void
    {
        $this->auth->guard('web')->logout();
    }
}
