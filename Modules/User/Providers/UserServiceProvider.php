<?php

namespace Modules\User\Providers;

use Core\CQRS\CommandBus;
use Core\Events\EventDispatcherInterface;
use Core\Support\ServiceProvider;
use Modules\User\Application\Commands\DeleteUserCommand;
use Modules\User\Application\Commands\UpdateUserProfileCommand;
use Modules\User\Application\Handlers\DeleteUserHandler;
use Modules\User\Application\Handlers\UpdateUserProfileHandler;
use Modules\User\Application\Listeners\FlushPermissionCacheForRoleUsers;
use Modules\User\Application\Listeners\FlushPermissionCacheOnProfileUpdate;
use Modules\User\Application\Listeners\FlushPermissionCacheOnRoleChange;
use Modules\User\Application\Listeners\FlushUserPermissionCache;
use Modules\User\Application\Policies\UserPolicy;
use Modules\User\Domain\Events\RoleAssignedToUser;
use Modules\User\Domain\Events\RolePermissionsChanged;
use Modules\User\Domain\Events\UserDeleted;
use Modules\User\Domain\Events\UserProfileUpdated;
use Modules\User\Domain\Services\AccessControlService;
use Modules\User\Infrastructure\Models\User;

class UserServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the module.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $listen = [
        UserDeleted::class => [
            FlushUserPermissionCache::class,
        ],
        UserProfileUpdated::class => [
            FlushPermissionCacheOnProfileUpdate::class,
        ],
        RoleAssignedToUser::class => [
            FlushPermissionCacheOnRoleChange::class,
        ],
        RolePermissionsChanged::class => [
            FlushPermissionCacheForRoleUsers::class,
        ],
    ];

    public function register(): void
    {
        $this->app->singleton(UserFinder::class, function () {
            return new UserFinder();
        });
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerCommandHandlers();
        $this->registerEventListeners();
    }

    private function registerPolicies(): void
    {
        /** @var AccessControlService $accessControlService */
        $accessControlService = $this->app->make(AccessControlService::class);
        // Đăng ký UserPolicy cho model User
        $accessControlService->policy(User::class, UserPolicy::class);
    }

    /**
     * Register all the command handlers for this module.
     */
    private function registerCommandHandlers(): void
    {
        /** @var CommandBus $commandBus */
        $commandBus = $this->app->make(CommandBus::class);

        $commandBus->register(UpdateUserProfileCommand::class, UpdateUserProfileHandler::class);
        $commandBus->register(DeleteUserCommand::class, DeleteUserHandler::class);
    }

    private function registerEventListeners(): void
    {
        /** @var EventDispatcherInterface $events */
        $events = $this->app->make(EventDispatcherInterface::class);

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }
}
