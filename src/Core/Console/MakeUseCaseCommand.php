<?php

namespace Core\Console;

use Core\Console\Contracts\CommandInterface;

class MakeUseCaseCommand implements CommandInterface
{
    public function signature(): string
    {
        return 'ddd:make-usecase {module} {name}';
    }

    public function handle(array $arguments): void
    {
        $module = ucfirst($arguments[0] ?? '');
        $name = ucfirst($arguments[1] ?? '');

        if (!$module || !$name) {
            echo "Bạn phải truyền tên module và tên UseCase. Ví dụ: `ddd:make-usecase User CreatePost`\n";
            return;
        }

        $path = __DIR__ . "/../../../Modules/{$module}/Application/UseCases/{$name}.php";
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = "<?php\n\nnamespace Modules\\{$module}\\Application\\UseCases;\n\nclass {$name}\n{\n    public function handle(): void\n    {\n        // TODO: implement use case\n    }\n}";

        file_put_contents($path, $stub);

        echo "UseCase {$name} đã được tạo trong module {$module}.\n";
    }
}
