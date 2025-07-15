<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeAllCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'make:command {name}';
    }

    public function description(): string
    {
        return 'Create a new console command class.';
    }

    public function handle(): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->io->error("Bạn phải truyền tên command. Ví dụ: `make:command MyCommand`\n");
            return 1;
        }

        $className = ucfirst($name);
        $fileName = $className . '.php';

        if (file_exists(base_path("src/Core/Console/{$fileName}"))) {
            $this->io->error("Command '{$className}' đã tồn tại. Không tạo lại.\n");
            return 1;
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
        $this->io->success("Command {$className} đã được tạo: {$url}\n");
        return 0;
    }
}
