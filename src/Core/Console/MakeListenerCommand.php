<?php

namespace Core\Console;

use Core\Application;

class MakeListenerCommand
{
    protected string $name = 'make:listener';
    protected string $description = 'Create a new event listener class.';

    public function __construct(protected Application $app)
    {
    }

    public function handle(array $arguments): int
    {
        $name = $arguments[0] ?? null;

        if (!$name) {
            echo "Error: Listener name is required.\nUsage: php bault make:listener SendWelcomeEmail\n";
            return 1;
        }

        $className = ucfirst($name);
        $path = $this->app->basePath("src/Listeners/{$className}.php");
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_exists($path)) {
            echo "Listener [{$className}] already exists.\n";
            return 1;
        }

        $stub = "<?php\n\nnamespace App\Listeners;\n\nclass {$className}\n{\n    public function handle(object \$event): void\n    {\n        //\n    }\n}\n";
        file_put_contents($path, $stub);

        echo "Listener [src/Listeners/{$className}.php] created successfully.\n";
        return 0;
    }
}