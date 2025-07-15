<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeUseCaseCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'ddd:make-usecase {module} {name}';
    }

    public function description(): string
    {
        return 'Create a new UseCase class within a module.';
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $name = $this->argument('name');

        if (!$module || !$name) {
            echo "Bạn phải truyền tên module và tên UseCase. Ví dụ: `ddd:make-usecase User CreatePost`\n";
            return 1;
        }

        $path = __DIR__ . "/../../../Modules/{$module}/Application/UseCases/{$name}.php";
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = "<?php\n\nnamespace Modules\\{$module}\\Application\\UseCases;\n\nclass {$name}\n{\n    public function handle(): void\n    {\n        // TODO: implement use case\n    }\n}";

        file_put_contents($path, $stub);

        $this->io->success("UseCase {$name} đã được tạo trong module {$module}.\n");

        return 1;
    }
}
