<?php

namespace Core\CLI;

use Core\Application;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Throwable;

class ConsoleKernel
{
    protected array $commands = [];
    protected ConsoleApplication $cli;
    protected Application $app;
    protected string $commandSuffix;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->cli = new ConsoleApplication('BaultFrame Console', '1.0.0');
        $this->commandSuffix = config('app.command_suffix', 'Command.php');
    }

    protected function loadCommands(): void
    {
        $registeredCommands = [];
        $paths = [];
        $paths = array_merge($paths, $this->scanCommands($this->app->basePath('src/Core/Console/Commands')));
        $paths = array_merge($paths, $this->scanCommands($this->app->basePath('app/Console/Commands')));
        foreach (glob($this->app->basePath('Modules/*'), GLOB_ONLYDIR) as $moduleDir) {
            $paths = array_merge($paths, $this->scanCommands($moduleDir . '/Console'));
        }

        foreach ($paths as $file) {
            $class = $this->fqcnFromFile($file);

            if ($class && class_exists($class) && is_subclass_of($class, Command::class)) {
                try {
                    $command = $this->app->make($class);

                    if (method_exists($command, 'setCoreApplication')) {
                        $command->setCoreApplication($this->app);
                    }

                    if (isset($registeredCommands[$command->getName()])) {
                        $this->app->make(LoggerInterface::class)->warning("Duplicate command name '{$command->getName()}' from '{$class}'. It will be overridden.");
                    }

                    $registeredCommands[$command->getName()] = $class;

                    $logger = $this->app->make(LoggerInterface::class);
                    $decoratedCommand = new LoggingCommandDecorator($command, $logger);

                    $this->cli->add($decoratedCommand);
                } catch (Throwable $e) {
                    if ($this->app->bound(LoggerInterface::class)) {
                        $logger = $this->app->make(LoggerInterface::class);
                        $logger->error("Không thể khởi tạo console command '{class}'.", [
                            'class'     => $class,
                            'exception' => $e,
                        ]);
                    }
                    $this->cli->add(new FailedCommand($class, $e));
                }
            }
        }
    }

    /**
     * Scan a directory for command files recursively.
     */
    private function scanCommands(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && str_ends_with($file->getFilename(), 'Command.php')) {
                $files[] = $file->getPathname();
            }
        }
        return array_filter($files, fn ($file) => str_ends_with(basename($file), $this->commandSuffix));
    }

    /**
     * Run the console application.
     */
    public function handle(): int
    {
        // Tải tất cả các command ngay trước khi chạy ứng dụng console.
        // Logic này giờ chỉ được thực thi trong môi trường CLI.
        $this->loadCommands();

        return $this->cli->run();
    }

    /**
     * Lấy ra Tên Class Đầy Đủ (FQCN) từ đường dẫn file.
     * Phương thức này sử dụng một map PSR-4 để làm cho logic linh hoạt và dễ bảo trì hơn.
     */
    private function fqcnFromFile(string $filePath): ?string
    {
        // Define PSR-4 mappings.
        // Order is important: more specific paths should come first.
        $psr4Mappings = [
            'Core\\' => $this->app->basePath('src/Core'),
            'Modules\\' => $this->app->basePath('Modules'),
            'App\\' => $this->app->basePath('src'), // Keep the App namespace pointing to src/
        ];

        $normalizedPath = str_replace('\\', '/', $filePath);

        foreach ($psr4Mappings as $namespace => $path) {
            $normalizedBasePath = rtrim(str_replace('\\', '/', $path), '/');
            if (str_starts_with($normalizedPath, $normalizedBasePath)) {
                // Get the relative class path from the PSR-4 root directory
                $relativeClassPath = substr($normalizedPath, strlen($normalizedBasePath) + 1);
                // Remove the .php extension
                $classPathWithoutExt = substr($relativeClassPath, 0, -4);
                // Reconstruct the full FQCN
                return $namespace . str_replace('/', '\\', $classPathWithoutExt);
            }
        }

        return null;
    }
}
