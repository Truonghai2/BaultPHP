<?php

namespace App\Providers;

use App\Exceptions\Handler;
use Core\Support\ServiceProvider;

class ExceptionServiceProvider extends ServiceProvider
{
    protected array $listener = [

    ];
    /**
     * Register the exception services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Handler::class, Handler::class);
    }

    public function boot(): void
    {
        // Việc set exception handler nên được thực hiện trong `boot`
        // để đảm bảo tất cả các service cần thiết đã được đăng ký.
        set_exception_handler([$this->app->make(Handler::class), 'handle']);
    }
}
