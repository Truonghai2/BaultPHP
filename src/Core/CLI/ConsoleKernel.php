<?php

namespace Core\CLI;

use Core\Application;
use Core\Contracts\Console\Kernel as KernelContract;
use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Support\Facades\Facade;
use DirectoryIterator;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * The ConsoleKernel is the central place for managing the CLI application.
 * It is responsible for discovering, registering, and executing commands.
 */
class ConsoleKernel implements KernelContract
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The Symfony Console application instance.
     */
    protected SymfonyApplication $console;

    /**
     * Indicates if the commands have been loaded.
     */
    protected bool $commandsLoaded = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->console = new SymfonyApplication('BaultPHP', '1.0.0');

        Facade::setFacadeApplication($this->app);
    }

    /**
     * Handle an incoming console command.
     */
    public function handle(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->bootstrap();
            $this->console->setAutoExit(false);

            return $this->console->run($input, $output);
        } catch (Throwable $e) {
            /** @var ExceptionHandler $handler */
            $handler = $this->app->make(ExceptionHandler::class);
            try {
                $handler->report(null, $e);
            } catch (Throwable $loggingException) {
                $handler->renderForConsole($output, $loggingException);
            }
            $handler->renderForConsole($output, $e);
            return 1;
        }
    }

    /**
     * Terminate the application.
     */
    public function terminate(InputInterface $input, int $status): void
    {
        // This is a good place to add any final cleanup logic,
        // such as closing database connections if they aren't managed by destructors.
    }

    /**
     * Get the underlying Symfony Console application.
     */
    public function getApplication(): SymfonyApplication
    {
        return $this->console;
    }

    /**
     * Bootstrap the console application.
     */
    protected function bootstrap(): void
    {
        if ($this->commandsLoaded) {
            return;
        }

        $this->app->boot();

        $this->registerCommands();

        $this->commandsLoaded = true;
    }

    /**
     * Register all of the commands in the given directory paths.
     */
    protected function registerCommands(): void
    {
        // In production, load commands from the cached file for performance.
        $cachePath = $this->app->bootstrapPath('cache/commands.php');
        if (file_exists($cachePath)) {
            $commands = require $cachePath;
            foreach ($commands as $commandClass) {
                if ($this->isInstantiableCommand($commandClass)) {
                    $this->console->add($this->app->make($commandClass));
                }
            }
            return;
        }

        // In development, discover commands by scanning directories.
        $paths = $this->getCommandPaths();
        $logger = $this->app->make(LoggerInterface::class);

        foreach ($paths as $namespace => $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->getClassFromPath($file, $path, $namespace);

                if (!$this->isInstantiableCommand($class)) {
                    continue;
                }

                try {
                    $this->console->add($this->app->make($class));
                } catch (Throwable $e) {
                    $this->console->add(new FailedCommand($class, $e));
                    $logger->error("Failed to load command '{$class}': {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Get the paths to the directories containing console commands.
     */
    protected function getCommandPaths(): array
    {
        $paths = [
            'Core\\Console\\Commands' => $this->app->basePath('src/Core/Console/Commands'),
        ];

        $modulesPath = $this->app->basePath('Modules');
        if (is_dir($modulesPath)) {
            foreach (new DirectoryIterator($modulesPath) as $moduleInfo) {
                if ($moduleInfo->isDir() && !$moduleInfo->isDot()) {
                    $moduleName = $moduleInfo->getFilename();
                    $moduleCommandPath = $moduleInfo->getPathname() . '/Console';

                    if (is_dir($moduleCommandPath)) {
                        $paths['Modules\\' . $moduleName . '\\Console'] = $moduleCommandPath;
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Derive the fully qualified class name from a file path.
     */
    private function getClassFromPath(SplFileInfo $file, string $basePath, string $baseNamespace): string
    {
        $relativePath = substr($file->getPathname(), strlen($basePath));
        $class = $baseNamespace . str_replace(
            ['/', '.php'],
            ['\\', ''],
            $relativePath,
        );

        return $class;
    }

    /**
     * Check if a given class is a valid, instantiable console command.
     */
    private function isInstantiableCommand(string $class): bool
    {
        return class_exists($class)
            && is_subclass_of($class, Command::class)
            && !(new ReflectionClass($class))->isAbstract();
    }
}
