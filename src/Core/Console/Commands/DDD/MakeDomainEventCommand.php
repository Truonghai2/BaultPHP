<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Create Domain Event for Event Sourcing
 */
class MakeDomainEventCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:domain-event {module : The module name} {name : The event name} {--aggregate= : Target aggregate}';
    }

    public function description(): string
    {
        return 'Create a new domain event for Event Sourcing.';
    }

    public function handle(): int
    {
        $module = ucfirst($this->argument('module'));
        $name = ucfirst($this->argument('name'));
        $aggregate = $this->option('aggregate');

        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' does not exist.");
            return self::FAILURE;
        }

        $this->io->info("Creating domain event [{$name}] in module [{$module}]...");

        $this->createDomainEvent($modulePath, $module, $name);

        $this->io->success("Domain event [{$name}] created successfully!");
        $this->io->comment("Next steps:");
        $this->io->listing([
            "1. Define event properties in the constructor",
            "2. Implement toArray() and fromArray() methods",
            "3. Add apply method in your aggregate: protected function apply{$name}(...)",
            "4. Record the event in aggregate: \$this->recordThat(new {$name}(...))",
        ]);

        return self::SUCCESS;
    }

    private function createDomainEvent(string $modulePath, string $module, string $name): void
    {
        $path = "{$modulePath}/Domain/Aggregates/Events/{$name}.php";
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Domain event [{$name}] already exists.");
            return;
        }

        $stub = $this->getEventStub($module, $name);
        file_put_contents($path, $stub);
        
        $this->io->text("  <fg=green>✓</> Created: Domain/Aggregates/Events/{$name}.php");
    }

    private function getEventStub(string $module, string $name): string
    {
        $namespace = "Modules\\{$module}\\Domain\\Aggregates\\Events";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\EventSourcing\DomainEvent;

/**
 * {$name} Event
 * 
 * TODO: Add event description
 */
class {$name} extends DomainEvent
{
    public function __construct(
        public readonly string \$aggregateId,
        // TODO: Add your event properties here
        // public readonly string \$propertyName,
    ) {
        parent::__construct(\$aggregateId);
    }

    /**
     * Convert event to array for serialization
     */
    public function toArray(): array
    {
        return [
            'aggregate_id' => \$this->aggregateId,
            // TODO: Add your properties here
            // 'property_name' => \$this->propertyName,
        ];
    }

    /**
     * Reconstruct event from array
     */
    public static function fromArray(array \$data): self
    {
        return new self(
            \$data['aggregate_id'],
            // TODO: Add your properties here
            // \$data['property_name'],
        );
    }
}
PHP;
    }
}

