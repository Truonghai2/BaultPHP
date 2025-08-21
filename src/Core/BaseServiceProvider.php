<?php

namespace Core;

use Core\Support\ServiceProvider;

/**
 * Class BaseServiceProvider
 *
 * A base service provider for modules to extend. It provides helper
 * methods for common module bootstrapping tasks like loading
 * configurations and migrations.
 *
 * @package Core
 */
class BaseServiceProvider extends ServiceProvider
{
    /**
     * The base path of the module.
     *
     * @var string|null
     */
    protected ?string $modulePath = null;

    /**
     * Register any application services.
     *
     * This method is intentionally left empty. Module-specific event-listener
     * mappings are loaded globally by the `EventServiceProvider` to avoid
     * redundant and potentially conflicting registrations.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Get the base path of the module.
     *
     * This method uses reflection to determine the directory of the child class,
     * assuming a standard module structure.
     *
     * @return string
     */
    protected function getModulePath(): string
    {
        if ($this->modulePath) {
            return $this->modulePath;
        }

        $reflector = new \ReflectionClass(static::class);
        // Assumes the provider is in `Modules/{ModuleName}/Providers/`
        return $this->modulePath = dirname($reflector->getFileName(), 2);
    }

    /**
     * Register a view namespace for the module.
     *
     * This allows you to use views like `view('namespace::view.name')`.
     *
     * @param string $path The path to the module's views directory.
     * @param string $namespace The namespace for the module's views.
     * @return void
     */
    protected function loadViewsFrom(string $path, string $namespace): void
    {
        /** @var \Core\View\Contracts\Factory $viewFactory */
        $viewFactory = $this->app->make('view');
        $viewFactory->addNamespace($namespace, $path);
    }

    /**
     * Register a path for the module's database migrations.
     *
     * This allows the `ddd:migrate` command to find and run the module's migrations.
     *
     * @param string $path The path to the module's migrations directory.
     * @return void
     */
    protected function loadMigrationsFrom(string $path): void
    {
        // Thay vì resolve 'migrator', chúng ta sẽ đăng ký đường dẫn vào config
        // để MigrationManager có thể tìm thấy khi lệnh ddd:migrate được chạy.
        $config = $this->app->make('config');

        // Lấy danh sách các đường dẫn migration đã có từ file config
        $paths = $config->get('database.migrations.paths', []);

        // Thêm đường dẫn mới của module vào danh sách
        $paths[] = $path;

        // Cập nhật lại config với danh sách đường dẫn mới, loại bỏ các giá trị trùng lặp
        $config->set('database.migrations.paths', array_unique($paths));
    }

    /**
     * Register the module's views using a conventional directory structure.
     * This helper assumes views are located in `resources/views` within the module.
     *
     * @param string $namespace The namespace for the module's views.
     * @return void
     */
    protected function loadModuleViews(string $namespace): void
    {
        $path = $this->getModulePath() . '/resources/views';
        $this->loadViewsFrom($path, $namespace);
    }
}
