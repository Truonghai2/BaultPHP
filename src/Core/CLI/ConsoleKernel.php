<?php

namespace Core\CLI;

use Core\Application;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;

class ConsoleKernel
{
    protected array $commands = [];
    protected ConsoleApplication $cli;
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->cli = new ConsoleApplication('BaultFrame Console', '1.0.0');
        $this->loadCommands();
    }

    protected function loadCommands(): void
    {
        // Quét command từ core và từ các module
        $paths = array_merge(
            glob($this->app->basePath('src/Core/Console/*Command.php')),
            glob($this->app->basePath('Modules/*/Console/*Command.php'))
        );

        foreach ($paths as $file) {
            $class = $this->fqcnFromFile($file);

            if ($class && class_exists($class) && is_subclass_of($class, Command::class)) {
                // Sử dụng DI container để khởi tạo command, cho phép inject dependencies
                $command = $this->app->make($class);
                $this->cli->add($command);
            }
        }
    }

    public function handle(array $argv): void
    {
        $this->cli->run();
    }

    /**
     * Lấy ra Tên Class Đầy Đủ (FQCN) từ đường dẫn file.
     * Tương tự phương thức trong RouteServiceProvider.
     */
    private function fqcnFromFile(string $filePath): ?string
    {
        $relativePath = str_replace(rtrim($this->app->basePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, '', $filePath);

        // Bỏ phần mở rộng .php
        $classPath = substr($relativePath, 0, -4);

        // Chuyển đổi dấu phân cách thư mục thành dấu phân cách namespace
        // src/Core/Console/MyCommand.php -> Core\Console\MyCommand
        // Modules/User/Console/UserCommand.php -> Modules\User\Console\UserCommand
        if (str_starts_with($classPath, 'src/')) {
            $classPath = substr($classPath, 4);
        }

        return str_replace(DIRECTORY_SEPARATOR, '\\', $classPath);
    }
}
