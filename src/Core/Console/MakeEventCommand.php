<?php

namespace Core\Console;

use Core\Console\Contracts\BaseCommand;

class MakeEventCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'make:event {name : The name of the event class}';
    }

    public function description(): string
    {
        return 'Create a new event class in the src/Events directory.';
    }

    /**
     * The core logic of the command.
     * This method creates a new event class.
     */
    public function fire(): void
    {
        $name = $this->argument('name');
        $this->createEventClass($name);
    }

    /**
     * Creates a new event class in the src/Events directory.
     */
    private function createEventClass(string $name): void
    {
        $this->io->title('Creating Event Class');
        $this->io->info("Creating event class: {$name}");

        $className = ucfirst($name);
        $path = base_path("src/Events/{$className}.php");
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Event [{$className}] already exists.");
            return;
        }

        $stub = "<?php\n\nnamespace App\Events;\n\nclass {$className}\n{\n    // public function __construct() {}\n}\n";
        file_put_contents($path, $stub);
        $this->io->success("Event [src/Events/{$className}.php] created successfully.");
    }

    /**
     * The entry point for the command.
     * This method executes the command logic.
     */
    public function handle(array $args = []): void
    {
        $this->io->title('Creating Event');
        $name = $this->argument('name');
        if (empty($name)) {
            $this->io->error('Event name is required.');
            return;
        }
        $this->fire();
    }
}