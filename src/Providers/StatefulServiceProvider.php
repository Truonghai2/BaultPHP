<?php

namespace App\Providers;

use Core\Auth\AuthManager;
use Core\Debug\DebugManager;
use Core\Foundation\StateResetter;
use Core\Support\ServiceProvider;
use Core\View\ViewFactory;

class StatefulServiceProvider extends ServiceProvider
{
    /**
     * Danh sách các service cần được reset sau mỗi request.
     * Chúng sẽ được tag và tự động inject vào StateResetter.
     *
     * @var string[]
     */
    protected array $statefulServices = [
        AuthManager::class,
        ViewFactory::class,
        DebugManager::class,
        // Thêm các service stateful khác của bạn ở đây
        // ví dụ: 'session', 'cookie.manager'
    ];

    public function register(): void
    {
        foreach ($this->statefulServices as $service) {
            $this->app->tag($service, 'stateful');
        }

        $this->app->singleton(StateResetter::class, function ($app) {
            return new StateResetter(
                $app->tagged('stateful'),
            );
        });

        $this->app->alias(StateResetter::class, 'state.resetter');
    }
}
