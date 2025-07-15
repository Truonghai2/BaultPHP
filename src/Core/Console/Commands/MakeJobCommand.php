<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeJobCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'ddd:make-job {module} {name}';
    }

    public function description(): string
    {
        return 'Create a new job class in the specified module.';
    }
    /**
     * The core logic of the command.
     * This method creates a new job class.
     */
    public function fire(): void
    {
        $module = $this->argument('module');
        $name = $this->argument('name');
        $this->createJobClass($module, $name);
    }

    
    /**
     * Creates a new job class in the specified module.
     */
    private function createJobClass(string $module, string $name): void
    {
        $this->io->title('Creating Job Class');
        $this->io->info("Creating job class: {$name} in module: {$module}");
        $jobName = ucfirst($name);
        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' does not exist.");
            return;
        }
        $path = "{$modulePath}/Application/Jobs/{$jobName}.php";
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        if (file_exists($path)) {
            $this->io->error("Job [{$jobName}] already exists in module [{$module}].");
            return;
        }
        $stub = "<?php\n\nnamespace Modules\\{$module}\\Application\\Jobs;\n\nuse Core\\Console\\Contracts\\BaseJob;\n\nclass {$jobName} extends BaseJob\n{\n    public function handle()\n    {\n        // Handle the job logic here.\n    }\n}\n";
        file_put_contents($path, $stub);
        $this->io->success("Job [{$jobName}] created successfully in module [{$module}].");
    }   

    /**
     * The entry point for the command.
     * This method runs the job creation logic.
     */
    public function handle(): int
    {
        $this->io->title('Creating Job');
        $module = $this->argument('module');
        $name = $this->argument('name');
        if (empty($module) || empty($name)) {
            $this->io->error('Module and job name are required.');
            return 1;
        }
        $this->fire();
        
        return 0;
    }
}