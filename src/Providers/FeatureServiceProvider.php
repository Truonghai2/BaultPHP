<?php

namespace App\Providers;

use App\Http\Middleware\FeatureMiddleware;
use Core\Features\FeatureManager;
use Core\Support\ServiceProvider;
use Core\View\Compiler;

class FeatureServiceProvider extends ServiceProvider
{
    /**
     * Register the feature flag services.
     */
    public function register(): void
    {
        // Register the FeatureManager as a singleton.
        $this->app->singleton('features', function ($app) {
            return new FeatureManager($app->make('config'));
        });

        $this->app->alias('features', FeatureManager::class);

        // Register the middleware for use in routing.
        $this->app->singleton(FeatureMiddleware::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerBladeDirectives();
    }

    /**
     * Register the custom Blade directives for feature flags.
     */
    protected function registerBladeDirectives(): void
    {
        /** @var Compiler $compiler */
        $compiler = $this->app->make(Compiler::class);

        $compiler->directive('feature', function (string $expression) {
            return "<?php if (\Core\Support\Facades\Feature::isEnabled({$expression})): ?>";
        });

        $compiler->directive('endfeature', function () {
            return '<?php endif; ?>';
        });
    }
}
