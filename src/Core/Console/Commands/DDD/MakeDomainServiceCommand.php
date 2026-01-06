<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Create Domain Service
 */
class MakeDomainServiceCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:domain-service {module : The module name} {name : The service name}';
    }

    public function description(): string
    {
        return 'Create a new domain service in the specified module.';
    }

    public function handle(): int
    {
        $module = ucfirst($this->argument('module'));
        $name = ucfirst($this->argument('name'));

        // Remove "Service" suffix if provided
        if (str_ends_with($name, 'Service')) {
            $name = substr($name, 0, -7);
        }

        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' does not exist.");
            return self::FAILURE;
        }

        $this->io->info("Creating domain service [{$name}DomainService] in module [{$module}]...");

        $this->createDomainService($modulePath, $module, $name);

        $this->io->success("Domain service [{$name}DomainService] created successfully!");
        $this->io->comment("Domain services contain pure business logic without infrastructure dependencies.");

        return self::SUCCESS;
    }

    private function createDomainService(string $modulePath, string $module, string $name): void
    {
        $path = "{$modulePath}/Domain/Services/{$name}DomainService.php";
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Domain service [{$name}DomainService] already exists.");
            return;
        }

        $stub = $this->getDomainServiceStub($module, $name);
        file_put_contents($path, $stub);
        
        $this->io->text("  <fg=green>✓</> Created: Domain/Services/{$name}DomainService.php");
    }

    private function getDomainServiceStub(string $module, string $name): string
    {
        $namespace = "Modules\\{$module}\\Domain\\Services";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

/**
 * {$name} Domain Service
 * 
 * Contains pure business logic that doesn't belong to a single entity.
 * No infrastructure dependencies (no DB, no HTTP, etc).
 */
class {$name}DomainService
{
    public function __construct()
    {
        // Domain services should only depend on other domain objects
        // (entities, value objects, other domain services)
    }

    /**
     * Example business rule method
     */
    public function canPerformAction(/* domain entities */): bool
    {
        // Pure business logic here
        // No database queries, no HTTP calls
        // Only domain rules
        
        return true;
    }

    /**
     * Example validation method
     */
    public function validate(/* domain entities */): void
    {
        // Business validation logic
        
        // if (/* invalid */) {
        //     throw new \DomainException('Validation failed');
        // }
    }

    /**
     * Example calculation method
     */
    public function calculate(/* parameters */): mixed
    {
        // Business calculations
        
        return null;
    }
}
PHP;
    }
}

