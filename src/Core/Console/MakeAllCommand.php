<?php

namespace Core\Console;

use Core\Console\Contracts\CommandInterface;

class MakeAllCommand implements CommandInterface
{
    public function signature(): string
    {
        return 'make:command {name}';
    }

    public function handle(array $arguments): void
    {
        $name = $arguments[0] ?? null;

        if (!$name) {
            echo "Bạn phải truyền tên command. Ví dụ: `make:command MyCommand`\n";
            return;
        }

        $className = ucfirst($name);
        $fileName = $className . '.php';

        if (file_exists(base_path("src/Core/Console/{$fileName}"))) {
            echo "Command '{$className}' đã tồn tại. Không tạo lại.\n";
            return;
        }

        $name = strtolower($name);
        $path = base_path("src/Core/Console/{$fileName}");
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = <<<PHP
<?php

namespace Core\Console;

use Core\Console\Contracts\CommandInterface;

class {$className} implements CommandInterface
{
    public function signature(): string
    {
        return '{$name}:run';
    }

    public function handle(array \$arguments): void
    {
        // TODO: implement command logic
        echo "Running {$className}...\\n";
    }
}
PHP;

        file_put_contents($path, $stub);

        $url = 'file://' . realpath($path);
        echo "Command {$className} đã được tạo: {$url}\n";
    }
}
