<?php

namespace Modules\User\Providers;

use Core\BaseServiceProvider;
use Core\Events\EventDispatcherInterface;
use Modules\User\Application\Listeners\SendWelcomeEmail;
use Modules\User\Domain\Events\UserWasCreated;

class UserServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadModuleViews('user');
        $this->registerEventListeners();
    }

    /**
     * Register the module's event listeners.
     */
    protected function registerEventListeners(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->app->make(EventDispatcherInterface::class);

        // Map the UserWasCreated event to its listener.
        $dispatcher->listen(UserWasCreated::class, SendWelcomeEmail::class);
    }
}
