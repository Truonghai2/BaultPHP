<?php

namespace Modules\User\Providers;

use Core\BaseServiceProvider;

class UserServiceProvider extends BaseServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected array $handlers = [
        \Modules\User\Application\Handlers\LoginUserHandler::class,
        \Modules\User\Application\Handlers\LogoutUserHandler::class,
        \Modules\User\Application\Handlers\RegisterUserHandler::class,
        \Modules\User\Application\Handlers\UserRole\AssignRoleToUserHandler::class,
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        foreach ($this->handlers as $handler) {
            $this->app->singleton($handler);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadModuleViews('user');
    }
}
