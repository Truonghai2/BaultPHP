<?php

namespace App\Providers;

use Core\CLI\ConsoleKernel;
use Core\Console\Contracts\BaseCommand;
use Core\Contracts\Console\Kernel as KernelContract;
use Core\FileSystem\Filesystem;
use Core\Support\ServiceProvider;

/**
 * This provider is responsible for bootstrapping the console application.
 * It binds the console kernel into the service container.
 */
class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Register the console kernel implementation.
     */
    public function register(): void
    {
        $this->app->singleton(KernelContract::class, function ($app) {
            return new ConsoleKernel($app);
        });

        $this->registerCommands();
    }

    /**
     * Đăng ký các lệnh console cốt lõi.
     *
     * Chúng ta sử dụng pattern `singleton` và `tag` để cho phép Kernel của console
     * tự động phát hiện và nạp các lệnh này.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $cachedCommandsPath = $this->app->getCachedCommandsPath();

            if (file_exists($cachedCommandsPath)) {
                $commands = require $cachedCommandsPath;
            } else {
                $commands = $this->discoverCommands();
            }

            foreach ($commands as $command) {
                $this->app->singleton($command);
                $this->app->tag($command, 'console.command');
            }
        }
    }

    /**
     * Discover all command classes within the application and modules.
     *
     * @return array<int, class-string>
     */
    public function discoverCommands(): array
    {
        /** @var Filesystem $files */
        $files = $this->app->make(Filesystem::class);
        $basePath = $this->app->basePath();

        $paths = [
            $basePath . '/src/Console/Commands',
            $basePath . '/src/Core/Console/Commands',
        ];

        $modulePaths = glob($basePath . '/Modules/*/Console/Commands', GLOB_ONLYDIR);
        if ($modulePaths) {
            $paths = array_merge($paths, $modulePaths);
        }

        $discoveredCommands = [];

        foreach ($paths as $path) {
            if (!$files->isDirectory($path)) {
                continue;
            }

            foreach ($files->allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->classFromFile($file, $basePath);

                if ($class && is_subclass_of($class, BaseCommand::class) && !(new \ReflectionClass($class))->isAbstract()) {
                    $discoveredCommands[] = $class;
                }
            }
        }

        return array_values(array_unique($discoveredCommands));
    }

    /**
     * Derives the full class name from a file path based on project structure.
     */
    protected function classFromFile(\SplFileInfo $file, string $basePath): ?string
    {
        $path = str_replace([$basePath, '.php'], '', $file->getRealPath());
        $path = trim($path, DIRECTORY_SEPARATOR);

        if (str_starts_with($path, 'src' . DIRECTORY_SEPARATOR . 'Core')) {
            // src\Core\Console\Commands\TinkerCommand -> Core\Console\Commands\TinkerCommand
            $class = 'Core' . substr($path, strlen('src' . DIRECTORY_SEPARATOR . 'Core'));
        } elseif (str_starts_with($path, 'src')) {
            // src\Console\Commands\MyCommand -> App\Console\Commands\MyCommand
            $class = 'App' . substr($path, strlen('src'));
        } elseif (str_starts_with($path, 'Modules')) {
            // Modules\User\Console\Commands\UserCommand -> Modules\User\Console\Commands\UserCommand
            $class = $path;
        } else {
            return null;
        }

        return str_replace(DIRECTORY_SEPARATOR, '\\', $class);
    }
}
