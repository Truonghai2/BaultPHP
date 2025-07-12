<?php

namespace Core\Console;

use Core\Application;

class MakeEventCommand
{
    protected string $name = 'make:event';
    protected string $description = 'Create a new event class.';

    public function __construct(protected Application $app)
    {
    }

    public function handle(array $arguments): int
    {
        $name = $arguments[0] ?? null;

        if (!$name) {
            echo "Error: Event name is required.\nUsage: php bault make:event UserRegistered\n";
            return 1;
        }

        $className = ucfirst($name);
        $path = $this->app->basePath("src/Events/{$className}.php");
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_exists($path)) {
            echo "Event [{$className}] already exists.\n";
            return 1;
        }

        $stub = "<?php\n\nnamespace App\Events;\n\nclass {$className}\n{\n    // public function __construct() {}\n}\n";
        file_put_contents($path, $stub);

        echo "Event [src/Events/{$className}.php] created successfully.\n";
        return 0;
    }
}