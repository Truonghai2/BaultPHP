<?php

declare(strict_types=1);

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Illuminate\Support\Str;

class MakeEventCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'ddd:make-event {module : The name of the module.} {name : The name of the event class.}';
    }

    public function description(): string
    {
        return 'Create a new domain event class for a module';
    }

    public function handle(): int
    {
        $module = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));

        $path = $this->app->basePath("Modules/{$module}/Domain/Events/{$name}.php");

        if (file_exists($path)) {
            $this->io->error("Event [{$name}] already exists in module [{$module}].");
            return self::FAILURE;
        }

        $this->makeDirectory(dirname($path));

        $namespace = "Modules\\{$module}\\Domain\\Events";

        $stub = file_get_contents(__DIR__ . '/../stubs/ddd.event.stub');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $name, $stub);

        file_put_contents($path, $stub);

        $this->io->success("Event [{$name}] created successfully in module [{$module}].");

        return self::SUCCESS;
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
