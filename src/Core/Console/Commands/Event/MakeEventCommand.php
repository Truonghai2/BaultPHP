<?php

namespace Core\Console\Commands\Event;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class MakeEventCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:event {name : The name of the event class} {--module= : The module to create the event in}';
    }

    public function description(): string
    {
        return 'Create a new event class in the application or a specific module.';
    }

    /**
     * The entry point for the command. This method executes the command logic.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->option('module');

        if (empty($name)) {
            $this->io->error('Event name is required.');
            return self::FAILURE;
        }

        $className = ucfirst($name);
        $namespace = 'App\\Events';
        $path = base_path("src/Events/{$className}.php");
        $locationDescription = "src/Events/{$className}.php";

        if ($module) {
            $module = ucfirst($module);
            $modulePath = base_path('Modules/' . $module);

            if (!is_dir($modulePath)) {
                $this->io->error("Module [{$module}] does not exist.");
                return self::FAILURE;
            }

            $namespace = "Modules\\{$module}\\Domain\\Events";
            $path = "{$modulePath}/Domain/Events/{$className}.php";
            $locationDescription = "Modules/{$module}/Domain/Events/{$className}.php";
        }

        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Event [{$className}] already exists at {$locationDescription}.");
            return self::FAILURE;
        }

        $stub = "<?php\n\nnamespace {$namespace};\n\nclass {$className}\n{\n    /**\n     * Create a new event instance.\n     */\n    public function __construct()\n    {\n        //\n    }\n}\n";
        file_put_contents($path, $stub);

        $this->io->success("Event [{$className}] created successfully.");
        $this->io->comment(" -> Located at: {$locationDescription}");

        return self::SUCCESS;
    }
}
