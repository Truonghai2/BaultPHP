<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeListenerCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'make:listener {name}';
    }

    public function description(): string
    {
        return 'Create a new event listener class.';
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (!$name) {
            echo "Error: Listener name is required.\nUsage: php bault make:listener SendWelcomeEmail\n";
            return 1;
        }

        $className = ucfirst($name);
        $path = base_path("src/Listeners/{$className}.php");
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            echo "Listener [{$className}] already exists.\n";
            return 1;
        }

        $stub = "<?php\n\nnamespace App\Listeners;\n\nclass {$className}\n{\n    public function handle(object \$event): void\n    {\n        //\n    }\n}\n";
        file_put_contents($path, $stub);

        $this->io->success("Listener [{$className}] created successfully.\n");
        return 0;
    }
}