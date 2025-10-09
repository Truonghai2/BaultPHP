<?php

namespace App\Providers;

use Core\Console\Contracts\BaseCommand;
use Core\Contracts\Console\Kernel as KernelContract;
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
            return new \Core\CLI\ConsoleKernel($app);
        });

        $this->registerCommands();
    }

    /**
     * Đăng ký các lệnh console cốt lõi.
     *
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
                if (class_exists($command) && (is_subclass_of($command, BaseCommand::class) || is_subclass_of($command, \Symfony\Component\Console\Command\Command::class))) {
                    $this->app->singleton($command);
                    $this->app->tag($command, 'console.command');
                }
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
        // We can't use the config service here as it might not be registered yet.
        // We must load the app config file directly.
        $appConfig = require $this->app->configPath('app.php');
        $coreProviders = $appConfig['providers'] ?? [];

        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));
        if ($moduleJsonPaths === false) {
            $moduleJsonPaths = [];
        }

        $commandPaths = [
            'Core\\Console\\Commands' => $this->app->basePath('src/Core/Console/Commands'),
            'App\\Console\\Commands' => $this->app->basePath('src/App/Console/Commands'),
        ];

        foreach ($moduleJsonPaths as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!empty($data['name']) && ($data['enabled'] ?? false) === true) {
                $moduleName = $data['name'];
                $moduleCommandPath = $this->app->basePath("Modules/{$moduleName}/Console");
                if (is_dir($moduleCommandPath)) {
                    $commandPaths["Modules\\{$moduleName}\\Console"] = $moduleCommandPath;
                }
            }
        }

        // Filter out paths that don't actually exist to prevent Finder exceptions.
        $existingPaths = array_filter($commandPaths, 'is_dir');

        if (empty($existingPaths)) {
            return [];
        }

        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->in(array_values($existingPaths))->name('*.php');

        $discoveredCommands = [];

        foreach ($finder as $file) {
            $class = $this->getClassFromFile($file, $commandPaths);
            if ($class && $this->isInstantiableCommand($class)) {
                $discoveredCommands[] = $class;
            }
        }

        return array_unique($discoveredCommands);
    }

    private function getClassFromFile(\SplFileInfo $file, array $commandPaths): ?string
    {
        foreach ($commandPaths as $namespace => $path) {
            if (str_starts_with($file->getRealPath(), realpath($path))) {
                $relativePath = ltrim(substr($file->getPathname(), strlen($path)), DIRECTORY_SEPARATOR);
                $class = $namespace . '\\' . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    $relativePath,
                );
                return $class;
            }
        }
        return null;
    }

    private function isInstantiableCommand(string $class): bool
    {
        try {
            $reflection = new \ReflectionClass($class);
            return !$reflection->isAbstract() && $reflection->isSubclassOf(\Symfony\Component\Console\Command\Command::class);
        } catch (\ReflectionException $e) {
            return false;
        }
    }
}
