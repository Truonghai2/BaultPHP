<?php

declare(strict_types=1);

namespace Modules\Cms\Providers;

/**
 * Blade Directives Service Provider
 *
 * Register custom Blade directives for CMS
 */
class BladeServiceProvider
{
    public function __construct(
        private readonly \Core\Application $app,
    ) {
    }

    public function boot(): void
    {
        $this->registerBlockDirectives();
    }

    /**
     * Register block rendering directives
     */
    protected function registerBlockDirectives(): void
    {
        /** @var \Core\View\Compiler $compiler */
        $compiler = $this->app->get(\Core\View\Compiler::class);

        // @blockRegion('header')
        $compiler->directive('blockRegion', function (string $expression) {
            return "<?php echo render_block_region({$expression}); ?>";
        });

        // @block(1) - render specific block by ID
        $compiler->directive('block', function (string $expression) {
            return "<?php echo render_block({$expression}); ?>";
        });
    }
}
