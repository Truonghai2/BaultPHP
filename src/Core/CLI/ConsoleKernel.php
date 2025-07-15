<?php

namespace Core\CLI;

use Core\Application;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Throwable;

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
            glob($this->app->basePath('src/Core/Console/Commands/*Command.php')),
            glob($this->app->basePath('Modules/*/Console/*Command.php'))
        );

        foreach ($paths as $file) {
            $class = $this->fqcnFromFile($file);

            if ($class && class_exists($class) && is_subclass_of($class, Command::class)) {
                try {
                    // Sử dụng DI container để khởi tạo command, cho phép inject dependencies
                    $command = $this->app->make($class);

                    // If it's one of our base commands, inject the core application instance.
                    // This avoids the signature conflict with Symfony's setApplication.
                    if (method_exists($command, 'setCoreApplication')) {
                        $command->setCoreApplication($this->app);
                    }
                    $this->cli->add($command);
                } catch (Throwable $e) {
                    // Ghi lỗi ra console nếu một command không thể được khởi tạo,
                    // nhưng không làm dừng toàn bộ ứng dụng CLI.
                    $this->cli->add(new FailedCommand($class, $e->getMessage()));
                }
            }
        }
    }

    /**
     * Run the console application.
     */
    public function handle(): int
    {
        return $this->cli->run();
    }

    /**
     * Lấy ra Tên Class Đầy Đủ (FQCN) từ đường dẫn file.
     * Phương thức này sử dụng một map PSR-4 để làm cho logic linh hoạt và dễ bảo trì hơn.
     */
    private function fqcnFromFile(string $filePath): ?string
    {
        // Định nghĩa các ánh xạ PSR-4.
        // Thứ tự rất quan trọng: các đường dẫn cụ thể hơn nên được đặt trước.
        $psr4Mappings = [
            'Core\\' => $this->app->basePath('src/Core'),
            'Modules\\' => $this->app->basePath('Modules'),
            // 'App\\' => $this->app->basePath('src'), // Có thể thêm các mapping khác ở đây
        ];

        $normalizedPath = str_replace('\\', '/', $filePath);

        foreach ($psr4Mappings as $namespace => $path) {
            $normalizedBasePath = rtrim(str_replace('\\', '/', $path), '/');
            if (str_starts_with($normalizedPath, $normalizedBasePath)) {
                // Lấy đường dẫn class tương đối so với thư mục gốc của PSR-4
                $relativeClassPath = substr($normalizedPath, strlen($normalizedBasePath) + 1);
                // Bỏ phần mở rộng .php
                $classPathWithoutExt = substr($relativeClassPath, 0, -4);
                // Tạo lại FQCN hoàn chỉnh
                return $namespace . str_replace('/', '\\', $classPathWithoutExt);
            }
        }

        return null;
    }
}
