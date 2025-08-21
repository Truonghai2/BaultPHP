<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use Modules\Post\Application\Policies\PostPolicy;
use Modules\Post\Infrastructure\Models\Post;
use Modules\User\Domain\Services\AccessControlService;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Các mapping từ Model sang Policy cho ứng dụng.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        // Post::class => PostPolicy::class,
        // Thêm các model và policy khác ở đây
        // \App\Models\Comment::class => \App\Policies\CommentPolicy::class,
    ];

    /**
     * Đăng ký bất kỳ service xác thực / phân quyền nào.
     */
    public function boot(): void
    {
        $this->registerPolicies();
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
