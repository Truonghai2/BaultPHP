<?php

namespace App\Providers;

use Core\Auth\AuthManager;
use Core\Support\ServiceProvider;
use Modules\User\Domain\Services\AccessControlService;
use Modules\User\Infrastructure\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Các mapping từ Model sang Policy cho ứng dụng.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('auth', function ($app) {
            return new AuthManager($app);
        });

        $this->app->alias('auth', AuthManager::class);
    }

    /**
     * Đăng ký bất kỳ service xác thực / phân quyền nào.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /** @var AccessControlService $acl */
        $acl = $this->app->make(AccessControlService::class);

        $acl->before(function (User $user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });
    }

    /**
     * Đăng ký các policy đã định nghĩa vào AccessControlService.
     */
    public function registerPolicies(): void
    {
        /** @var AccessControlService $acl */
        $acl = $this->app->make(AccessControlService::class);

        foreach ($this->policies as $model => $policy) {
            $acl->policy($model, $policy);
        }
    }
}
