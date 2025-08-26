<?php

declare(strict_types=1);

namespace Core\Console\Commands\DDD;

use Core\Console\Contracts\BaseCommand;
use Illuminate\Support\Str;

class MakeFactoryCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'ddd:make-factory {name : The name of the factory class (e.g., UserFactory).} {--m|model= : The model that the factory is for (e.g., User).}';
    }

    public function description(): string
    {
        return 'Create a new model factory';
    }

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $modelName = $this->option('model');

        if (!$modelName) {
            $modelName = str_replace('Factory', '', $name);
        }
        $modelName = Str::studly($modelName);

        $modelFqcn = $this->findModelFqcn($modelName);

        if (!$modelFqcn) {
            $this->io->error("Model [{$modelName}] not found in any module. Please ensure the model exists.");
            return self::FAILURE;
        }

        $path = $this->app->basePath("database/factories/{$name}.php");

        if (file_exists($path)) {
            $this->io->error("Factory [{$name}] already exists.");
            return self::FAILURE;
        }

        $this->makeDirectory(dirname($path));

        $stub = file_get_contents(__DIR__ . '/../stubs/ddd.factory.stub');
        $stub = str_replace('{{ class }}', $name, $stub);
        $stub = str_replace('{{ modelNamespace }}', $modelFqcn, $stub);
        $stub = str_replace('{{ modelClass }}', $modelName, $stub);

        file_put_contents($path, $stub);

        $this->io->success("Factory [{$name}] created successfully.");

        return self::SUCCESS;
    }

    private function findModelFqcn(string $modelName): ?string
    {
        $modulesPath = $this->app->basePath('Modules');
        if (!is_dir($modulesPath)) {
            return null;
        }

        $dirIterator = new \RecursiveDirectoryIterator($modulesPath, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            if ($file->getBasename('.php') === $modelName) {
                $content = file_get_contents($file->getRealPath());
                if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
                    return $matches[1] . '\\' . $modelName;
                }
            }
        }

        return null;
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
