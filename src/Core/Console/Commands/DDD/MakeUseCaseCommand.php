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

            // Use separate directories for Command/Query handlers (CQRS pattern)
            $dtoSubPath = $isQuery ? 'Queries' : 'Commands';
            $handlerSubPath = $isQuery ? 'QueryHandlers' : 'CommandHandlers';
            
            $dtoPath = base_path("Modules/{$module}/Application/{$dtoSubPath}/{$dtoClassName}.php");
            $handlerPath = base_path("Modules/{$module}/Application/{$handlerSubPath}/{$handlerClassName}.php");

            if (file_exists($dtoPath) || file_exists($handlerPath)) {
                throw new InvalidArgumentException("Use case [{$name}] already exists in module [{$module}].");
            }

            $this->createDtoFile($dtoPath, $module, $dtoSubPath, $dtoClassName, $isQuery);
            $this->createHandlerFile($handlerPath, $module, $dtoSubPath, $handlerSubPath, $dtoClassName, $handlerClassName, $isQuery);

            $this->io->success("Use Case [{$name}] created successfully in module [{$module}].");
            $this->io->comment("Files created:");
            $this->io->listing([
                "Application/{$dtoSubPath}/{$dtoClassName}.php",
                "Application/{$handlerSubPath}/{$handlerClassName}.php",
            ]);

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

    private function createDtoFile(string $path, string $module, string $subPath, string $className, bool $isQuery): void
    {
        // Generate inline stub for better control
        $namespace = "Modules\\{$module}\\Application\\{$subPath}";
        $type = $isQuery ? 'Query' : 'Command';
        
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

/**
 * {$className}
 * 
 * {$type} DTO for use case.
 */
class {$className}
{
    public function __construct(
        // Define your parameters here
        // public readonly string \$id,
        // public readonly string \$name,
    ) {}
}
PHP;

        $this->createFile($path, $content);
    }

    private function createHandlerFile(string $path, string $module, string $dtoSubPath, string $handlerSubPath, string $dtoClassName, string $handlerClassName, bool $isQuery): void
    {
        $namespace = "Modules\\{$module}\\Application\\{$handlerSubPath}";
        $dtoImport = "Modules\\{$module}\\Application\\{$dtoSubPath}\\{$dtoClassName}";
        $dtoVariable = lcfirst($dtoClassName);
        $type = $isQuery ? 'Query' : 'Command';
        
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$dtoImport};

/**
 * {$handlerClassName}
 * 
 * Handles {$dtoClassName} {strtolower($type)}.
 */
class {$handlerClassName}
{
    public function __construct(
        // Inject dependencies here
        // private YourRepository \$repository,
    ) {}

    /**
     * Handle the {strtolower($type)}
     */
    public function handle({$dtoClassName} \${$dtoVariable}): mixed
    {
        // Implement your business logic here
        
        return null;
    }
}
PHP;

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
