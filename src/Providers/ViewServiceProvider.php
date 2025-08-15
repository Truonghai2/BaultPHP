<?php

namespace App\Providers;

use Core\Contracts\View\Factory as ViewFactoryContract;
use Core\Filesystem\Filesystem;
use Core\Support\ServiceProvider;
use Core\View\Compiler;
use Core\View\ViewFactory;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các service liên quan đến view.
     */
    public function register(): void
    {
        $this->registerCompiler();
        $this->registerFactory();
    }

    /**
     * Đăng ký View Compiler vào container.
     */
    protected function registerCompiler(): void
    {
        $this->app->singleton(Compiler::class, function ($app) {
            $cachePath = config('view.compiled');

            return new Compiler($app->make(Filesystem::class), $cachePath);
        });
    }

    /**
     * Đăng ký View Factory vào container.
     */
    protected function registerFactory(): void
    {
        $this->app->singleton(ViewFactoryContract::class, function ($app) {
            $compiler = $app->make(Compiler::class);
            $files = $app->make(Filesystem::class);
            $paths = config('view.paths');

            return new ViewFactory($compiler, $files, $paths);
        });

        $this->app->alias(ViewFactoryContract::class, 'view');
    }
}
