<?php

declare(strict_types=1);

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeRepositoryCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'ddd:make-repository {module : The name of the module.} {name : The name of the repository class (e.g., UserRepository).}';
    }

    public function description(): string
    {
        return 'Create a new repository and interface for a module';
    }

    public function handle(): int
    {
        $module = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));

        if (!Str::endsWith($name, 'Repository')) {
            $name .= 'Repository';
        }

        $interfaceName = $name . 'Interface';
        $modelName = Str::studly(str_replace('Repository', '', $name));

        $this->createInterface($this->io, $module, $interfaceName, $modelName);
        $this->createClass($this->io, $module, $name, $interfaceName, $modelName);
        $this->addBindingToServiceProvider($this->io, $module, $name, $interfaceName);

        $this->io->success("Repository [{$name}] and Interface [{$interfaceName}] created successfully in module [{$module}].");

        return self::SUCCESS;
    }

    private function createInterface(SymfonyStyle $io, string $module, string $interfaceName, string $modelName): void
    {
        $path = $this->app->basePath("Modules/{$module}/Domain/Repositories/{$interfaceName}.php");

        if (file_exists($path)) {
            $io->warning("Interface [{$interfaceName}] already exists.");
            return;
        }

        $this->makeDirectory(dirname($path));

        $stub = file_get_contents(__DIR__ . '/../stubs/ddd.repository.interface.stub');

        $replacements = [
            '{{ namespace }}' => "Modules\\{$module}\\Domain\\Repositories",
            '{{ class }}' => $interfaceName,
            '{{ model_namespace }}' => "Modules\\{$module}\\Infrastructure\\Models\\{$modelName}",
            '{{ model }}' => $modelName,
        ];

        $stub = str_replace(array_keys($replacements), array_values($replacements), $stub);

        file_put_contents($path, $stub);
    }

    private function createClass(SymfonyStyle $io, string $module, string $className, string $interfaceName, string $modelName): void
    {
        $path = $this->app->basePath("Modules/{$module}/Infrastructure/Repositories/{$className}.php");

        if (file_exists($path)) {
            $io->warning("Class [{$className}] already exists.");
            return;
        }

        $this->makeDirectory(dirname($path));

        $stub = file_get_contents(__DIR__ . '/../stubs/ddd.repository.stub');

        $replacements = [
            '{{ namespace }}' => "Modules\\{$module}\\Infrastructure\\Repositories",
            '{{ class }}' => $className,
            '{{ interface_namespace }}' => "Modules\\{$module}\\Domain\\Repositories\\{$interfaceName}",
            '{{ interface }}' => $interfaceName,
            '{{ model_namespace }}' => "Modules\\{$module}\\Infrastructure\\Models\\{$modelName}",
            '{{ model }}' => $modelName,
        ];

        $stub = str_replace(array_keys($replacements), array_values($replacements), $stub);

        file_put_contents($path, $stub);
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Automatically add the repository binding to the module's service provider.
     */
    private function addBindingToServiceProvider(SymfonyStyle $io, string $module, string $className, string $interfaceName): void
    {
        $providerName = "{$module}ServiceProvider";
        $providerPath = $this->app->basePath("Modules/{$module}/Providers/{$providerName}.php");

        if (!file_exists($providerPath)) {
            $io->warning("Could not find Service Provider at [{$providerPath}]. Skipping automatic binding.");
            $io->comment("Please bind the repository manually: \$this->app->bind({$interfaceName}::class, {$className}::class);");
            return;
        }

        $content = file_get_contents($providerPath);

        $interfaceFqcn = "Modules\\{$module}\\Domain\\Repositories\\{$interfaceName}";
        $classFqcn = "Modules\\{$module}\\Infrastructure\\Repositories\\{$className}";

        if (str_contains($content, $interfaceFqcn . '::class')) {
            $io->comment("Binding for [{$interfaceName}] already seems to exist in [{$providerName}]. Skipping.");
            return;
        }

        $bindingCode = <<<PHP

        \$this->app->bind(
            \\{$interfaceFqcn}::class,
            \\{$classFqcn}::class
        );
PHP;

        $registerMethodRegex = '/(public\s+function\s+register\s*\(\s*\)\s*:\s*void\s*\{)/m';
        $newContent = preg_replace($registerMethodRegex, '$1' . $bindingCode, $content, 1, $count);

        if ($count > 0 && $newContent !== null) {
            file_put_contents($providerPath, $newContent);
            $io->info("Binding successfully added to [{$providerName}].");
        } else {
            $io->warning("Could not find a `register(): void` method in [{$providerName}]. Please add the binding manually.");
        }
    }
}
