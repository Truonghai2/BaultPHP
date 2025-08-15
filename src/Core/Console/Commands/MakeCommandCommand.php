<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeCommandCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     */
    public function signature(): string
    {
        return 'make:command {name : The name of the command class} {--module= : Create the command in a specific module} {--core : Create the command in the Core namespace}';
    }

    /**
     * The console command description.
     */
    public function description(): string
    {
        return 'Create a new console command class';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->option('module');
        $isCore = $this->option('core');

        if ($module && $isCore) {
            $this->error('The --module and --core options cannot be used together.');
            return self::FAILURE;
        }

        // Validate class name format (StudlyCase)
        if (!preg_match('/^[A-Z][a-zA-Z0-9_]*$/', $name)) {
            $this->error('Invalid command name. Please use StudlyCase format (e.g., SendDailyReport).');
            return self::FAILURE;
        }

        $className = $name;
        $namespace = 'App\\Console\\Commands';
        $path = app_path("Console/Commands/{$className}.php");
        $locationDescription = 'src/Console/Commands/';

        if ($isCore) {
            $namespace = 'Core\\Console\\Commands';
            $path = base_path("src/Core/Console/Commands/{$className}.php");
            $locationDescription = 'src/Core/Console/Commands/';
        } elseif ($module) {
            $module = ucfirst($module);
            $modulePath = base_path('Modules/' . $module);
            if (!is_dir($modulePath)) {
                $this->error("Module [{$module}] does not exist.");
                return self::FAILURE;
            }
            $namespace = "Modules\\{$module}\\Console\\Commands";
            $path = "{$modulePath}/Console/Commands/{$className}.php";
            $locationDescription = "Modules/{$module}/Console/Commands/";
        }

        if (file_exists($path)) {
            $this->error("Command class [{$className}] already exists!");
            return self::FAILURE;
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStubContent();

        // Generate a command name from the class name, e.g., SendDailyReport -> app:send-daily-report
        $commandName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
        $commandPrefix = $isCore ? 'core' : ($module ? strtolower($module) : 'app');
        $fullCommandName = "{$commandPrefix}:{$commandName}";

        $stub = str_replace(
            ['{{namespace}}', '{{className}}', '{{commandName}}'],
            [$namespace, $className, $fullCommandName],
            $stub,
        );

        file_put_contents($path, $stub);

        $this->info("Command [{$className}] created successfully.");
        $this->comment(" -> Located at: {$locationDescription}{$className}.php");
        $this->comment(" -> To run, use: <fg=yellow>php cli {$fullCommandName}</>");

        return self::SUCCESS;
    }

    /**
     * Ensure the directory for the file exists.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    /**
     * Get the path to the command stub file.
     */
    protected function getStubPath(): string
    {
        return base_path('src/Core/Console/Commands/stubs/command.stub');
    }

    /**
     * Get the command stub content.
     */
    protected function getStubContent(): string
    {
        $path = $this->getStubPath();

        if (!file_exists($path)) {
            throw new \RuntimeException("Stub file not found at: {$path}");
        }

        return file_get_contents($path);
    }
}
