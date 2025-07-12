<?php

namespace Core\Console;

use Core\Console\Contracts\CommandInterface;

class MakeEventCommand implements CommandInterface
{
    public function signature(): string
    {
        return 'make:event {name}';
    }

    public function handle(array $arguments): void
    {
        $name = $arguments[0] ?? null;

        if (!$name) {
            echo "Error: Event name is required.\nUsage: php bault make:event UserRegistered\n";
            return;
        }

        $className = ucfirst($name);
        $path = base_path("src/Events/{$className}.php");
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            echo "Event [{$className}] already exists.\n";
            return;
        }

        $stub = "<?php\n\nnamespace App\Events;\n\nclass {$className}\n{\n    // public function __construct() {}\n}\n";
        file_put_contents($path, $stub);

        echo "Event [src/Events/{$className}.php] created successfully.\n";
    }
}