<?php

namespace Core\Console\Commands\Listener;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class MakeListenerCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:listener {name : The name of the listener class} {--module= : The module to create the listener in}';
    }

    public function description(): string
    {
        return 'Create a new event listener class in the application or a specific module.';
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->option('module');

        if (!$name) {
            $this->io->error('Listener name is required.');
            return self::FAILURE;
        }

        $className = ucfirst($name);
        $namespace = 'App\\Listeners';
        $path = base_path("src/Listeners/{$className}.php");
        $locationDescription = "src/Listeners/{$className}.php";

        if ($module) {
            $module = ucfirst($module);
            $modulePath = base_path('Modules/' . $module);

            if (!is_dir($modulePath)) {
                $this->io->error("Module [{$module}] does not exist.");
                return self::FAILURE;
            }

            $namespace = "Modules\\{$module}\\Application\\Listeners";
            $path = "{$modulePath}/Application/Listeners/{$className}.php";
            $locationDescription = "Modules/{$module}/Application/Listeners/{$className}.php";
        }

        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Listener [{$className}] already exists at {$locationDescription}.");
            return self::FAILURE;
        }

        $stub = "<?php\n\nnamespace {$namespace};\n\nclass {$className}\n{\n    public function handle(object \$event): void\n    {\n        //\n    }\n}\n";
        file_put_contents($path, $stub);

        $this->io->success("Listener [{$className}] created successfully.");
        $this->io->comment(" -> Located at: {$locationDescription}");

        return self::SUCCESS;
    }
}
