<?php

namespace Modules\User\Providers;

use Core\BaseServiceProvider;

class UserServiceProvider extends BaseServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected array $handlers = [
        \Modules\User\Application\CommandHandlers\Auth\LoginHandler::class,
        \Modules\User\Application\CommandHandlers\Auth\LogoutHandler::class,
        \Modules\User\Application\CommandHandlers\Auth\RegisterHandler::class,
        \Modules\User\Application\CommandHandlers\User\AssignRoleHandler::class,
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
