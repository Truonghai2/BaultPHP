<?php

namespace App\Providers;

use Core\Frontend\ComponentRenderer;
use Core\Events\IlluminateViewEventAdapter;
use Core\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher as IlluminateDispatcherContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind other necessary components for the view system
        $this->app->singleton(Filesystem::class, fn () => new Filesystem());

        // Bind the Illuminate Dispatcher Contract to our custom adapter.
        // This adapter makes our native event system compatible with illuminate/view.
        $this->app->singleton(IlluminateDispatcherContract::class, fn ($app) => 
            new IlluminateViewEventAdapter($app->make(\Core\Events\EventDispatcherInterface::class))
        );
        // Bind the view factory as a singleton
        $this->app->singleton('view', function ($app) {
            // The resolver is responsible for resolving a view engine instance.
            $resolver = new EngineResolver();

            // The Blade compiler
            $bladeCompiler = new BladeCompiler(
                $app->make(Filesystem::class),
                $app->bootstrapPath('cache/views') // Path to cache compiled views
            );

            // Register the Blade engine for .blade.php files
            $resolver->register('blade', function () use ($bladeCompiler) {
                return new CompilerEngine($bladeCompiler);
            });

            // Register the plain PHP engine for .php files
            $resolver->register('php', function () use ($app) {
                // The PhpEngine requires a Filesystem instance to read view files.
                return new PhpEngine($app->make(Filesystem::class));
            });

            // The finder is responsible for locating the view files.
            $finder = new FileViewFinder(
                $app->make(Filesystem::class),
                [resource_path('views')] // Array of paths to look for views
            );

            // The factory is the main class that orchestrates the view rendering.
            $factory = new Factory($resolver, $finder, $app->make(IlluminateDispatcherContract::class));

            // This is the crucial part: It tells the finder to look for both .blade.php and .php files.
            $factory->addExtension('blade.php', 'blade');
            $factory->addExtension('php', 'php');

            return $factory;
        });
    }

    public function boot(): void
    {
        $this->registerBladeDirectives();
    }

    protected function registerBladeDirectives(): void
    {
        /** @var \Illuminate\View\Factory $viewFactory */
        $viewFactory = $this->app->make('view');

        /** @var \Illuminate\View\Compilers\BladeCompiler $bladeCompiler */
        $bladeCompiler = $viewFactory->getEngineResolver()->resolve('blade')->getCompiler();

        $bladeCompiler->directive('component', fn ($expression) => "<?php echo \\" . ComponentRenderer::class . "::render({$expression}); ?>");
    }
}
