<?php

namespace Core\Console;

use Core\Console\Contracts\CommandInterface;

class MakeListenerCommand implements CommandInterface
{
    public function signature(): string
    {
        return 'make:listener {name}';
    }

    public function handle(array $arguments): void
    {
        $name = $arguments[0] ?? null;

        if (!$name) {
            echo "Error: Listener name is required.\nUsage: php bault make:listener SendWelcomeEmail\n";
            return;
        }

        $className = ucfirst($name);
        $path = base_path("src/Listeners/{$className}.php");
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            echo "Listener [{$className}] already exists.\n";
            return;
        }

        $stub = "<?php\n\nnamespace App\Listeners;\n\nclass {$className}\n{\n    public function handle(object \$event): void\n    {\n        //\n    }\n}\n";
        file_put_contents($path, $stub);

        echo "Listener [src/Listeners/{$className}.php] created successfully.\n";
    }
}