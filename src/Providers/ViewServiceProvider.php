<?php

namespace App\Providers;

use Core\Contracts\View\Factory as ViewFactoryContract;
use Core\Extension\CoreExtensionPoints;
use Core\Extension\ExtensionRegistry;
use Core\FileSystem\Filesystem;
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

    public function boot(): void
    {
        $this->registerBladeDirectives();
        $this->shareGlobalViewData();
    }

    /**
     * Call the view.global_data extension point and share all collected data
     * with every view through ViewFactory::share().
     *
     * This runs once at boot time so the data is available for all requests
     * handled by the same Swoole worker process.
     */
    protected function shareGlobalViewData(): void
    {
        if (!$this->app->bound(ExtensionRegistry::class)) {
            return;
        }

        /** @var ExtensionRegistry $registry */
        $registry = $this->app->make(ExtensionRegistry::class);

        if (!$registry->isDeclared(CoreExtensionPoints::VIEW_GLOBAL_DATA)) {
            return;
        }

        $globalData = $registry->collect(CoreExtensionPoints::VIEW_GLOBAL_DATA);

        if (!empty($globalData)) {
            /** @var ViewFactory $view */
            $view = $this->app->make(ViewFactory::class);
            $view->share($globalData);
        }
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
        $this->app->singleton(ViewFactory::class, function ($app) {
            $compiler = $app->make(Compiler::class);
            $files = $app->make(Filesystem::class);
            $paths = config('view.paths');

            return new ViewFactory($compiler, $files, $paths);
        });

        $this->app->alias(ViewFactory::class, ViewFactoryContract::class);
        $this->app->alias(ViewFactory::class, 'view');
    }

    protected function registerBladeDirectives(): void
    {
        /** @var \Core\View\Compiler $compiler */
        $compiler = $this->app->make(Compiler::class);

        $compiler->directive('can', function ($expression) {
            return "<?php if (auth()->check() && auth()->user()->can{$expression}): ?>";
        });

        $compiler->directive('cannot', function ($expression) {
            return "<?php if (auth()->check() && !auth()->user()->can{$expression}): ?>";
        });

        // @elsecan('permission', $context)
        $compiler->directive('elsecan', function ($expression) {
            return "<?php elseif (auth()->check() && auth()->user()->can{$expression}): ?>";
        });

        $compiler->directive('endcan', function () {
            return '<?php endif; ?>';
        });
    }
}
