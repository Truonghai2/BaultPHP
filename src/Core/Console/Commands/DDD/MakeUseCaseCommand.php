<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use InvalidArgumentException;

class MakeUseCaseCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'ddd:make-usecase {module : The name of the module} {name : The name of the use case} {--query : Generate a Query and Handler instead of a Command}';
    }

    public function description(): string
    {
        return 'Create a new Use Case (Command/Query and Handler) within a module.';
    }

    public function handle(): int
    {
        try {
            $module = ucfirst($this->argument('module'));
            $name = ucfirst($this->argument('name'));
            $isQuery = $this->option('query');

            $this->validateModuleExists($module);

            $useCaseType = $isQuery ? 'Query' : 'Command';
            $dtoClassName = "{$name}{$useCaseType}";
            $handlerClassName = "{$name}Handler";

            $dtoSubPath = $isQuery ? 'Queries' : 'Commands';
            $dtoPath = base_path("Modules/{$module}/Application/{$dtoSubPath}/{$dtoClassName}.php");
            $handlerPath = base_path("Modules/{$module}/Application/Handlers/{$handlerClassName}.php");

            if (file_exists($dtoPath) || file_exists($handlerPath)) {
                throw new InvalidArgumentException("Use case [{$name}] already exists in module [{$module}].");
            }

            $this->createDtoFile($dtoPath, $module, $dtoSubPath, $dtoClassName);
            $this->createHandlerFile($handlerPath, $module, $dtoSubPath, $dtoClassName, $handlerClassName);

            $this->io->success("Use Case [{$name}] created successfully in module [{$module}].");
            $this->io->comment("-> Remember to register the handler in your CommandBus (e.g., in CqrsServiceProvider or your module's provider).");

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->io->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function validateModuleExists(string $module): void
    {
        if (!is_dir(base_path("Modules/{$module}"))) {
            throw new InvalidArgumentException("Module [{$module}] does not exist.");
        }
    }

    private function getStubContent(string $name): string
    {
        $path = base_path("src/Core/Console/Commands/stubs/{$name}.stub");
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Stub file [{$name}.stub] not found.");
        }
        return file_get_contents($path);
    }

    private function createDtoFile(string $path, string $module, string $subPath, string $className): void
    {
        $stub = $this->getStubContent('usecase.dto');

        $content = str_replace(
            ['{{ namespace }}', '{{ class_name }}'],
            [
                "Modules\\{$module}\\Application\\{$subPath}",
                $className,
            ],
            $stub,
        );

        $this->createFile($path, $content);
    }

    private function createHandlerFile(string $path, string $module, string $dtoSubPath, string $dtoClassName, string $handlerClassName): void
    {
        $stub = $this->getStubContent('usecase.handler');

        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ dto_import }}',
                '{{ class_name }}',
                '{{ dto_class }}',
                '{{ dto_variable }}',
            ],
            [
                "Modules\\{$module}\\Application\\Handlers",
                "Modules\\{$module}\\Application\\{$dtoSubPath}\\{$dtoClassName}",
                $handlerClassName,
                $dtoClassName,
                lcfirst($dtoClassName),
            ],
            $stub,
        );

        $this->createFile($path, $content);
    }

    private function createFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($path, $content);
        $this->io->info('Created: ' . str_replace(base_path() . '/', '', $path));
    }
}
