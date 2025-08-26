<?php

declare(strict_types=1);

namespace Core\Console\Commands\DDD;

use Core\Console\Contracts\BaseCommand;
use Illuminate\Support\Str;

class MakeListenerCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'ddd:make-listener {module : The name of the module.} {name : The name of the listener class.} {--e|event= : The event class to listen for.}';
    }

    public function description(): string
    {
        return 'Create a new event listener class for a module';
    }

    public function handle(): int
    {
        $module = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));
        $event = $this->option('event');

        $path = $this->app->basePath("Modules/{$module}/Application/Listeners/{$name}.php");

        if (file_exists($path)) {
            $this->io->error("Listener [{$name}] already exists in module [{$module}].");
            return self::FAILURE;
        }

        $this->makeDirectory(dirname($path));

        $namespace = "Modules\\{$module}\\Application\\Listeners";

        $stub = file_get_contents(__DIR__ . '/../stubs/ddd.listener.stub');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $name, $stub);

        if ($event) {
            $eventClass = Str::studly($event);
            $eventFqcn = "Modules\\{$module}\\Domain\\Events\\{$eventClass}";
            $useEvent = "use {$eventFqcn};";
            $eventHint = $eventClass;
        } else {
            $useEvent = '';
            $eventHint = 'object';
        }

        $stub = str_replace('{{ use_event }}', $useEvent, $stub);
        $stub = str_replace('{{ event_hint }}', $eventHint, $stub);

        file_put_contents($path, $stub);

        $this->io->success("Listener [{$name}] created successfully in module [{$module}].");
        $this->io->info("Don't forget to register this listener in your EventServiceProvider!");

        return self::SUCCESS;
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
