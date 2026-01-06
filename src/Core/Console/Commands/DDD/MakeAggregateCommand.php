<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Create Event Sourcing Aggregate
 */
class MakeAggregateCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:aggregate {module : The module name} {name : The aggregate name}';
    }

    public function description(): string
    {
        return 'Create a new Event Sourcing aggregate in the specified module.';
    }

    public function handle(): int
    {
        $module = ucfirst($this->argument('module'));
        $name = ucfirst($this->argument('name'));

        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' does not exist.");
            return self::FAILURE;
        }

        $this->io->info("Creating Event Sourcing aggregate [{$name}] in module [{$module}]...");

        $this->createAggregate($modulePath, $module, $name);
        $this->createExampleEvents($modulePath, $module, $name);

        $this->io->success("Aggregate [{$name}] created successfully!");
        $this->io->comment('Next steps:');
        $this->io->listing([
            '1. Define domain events in Domain/Aggregates/Events/',
            '2. Implement aggregate methods (business logic)',
            '3. Create apply* methods for each event',
            "4. Create AggregateService: php cli make:aggregate-service {$module} {$name}",
            '5. Configure in config/event-sourcing.php',
        ]);

        return self::SUCCESS;
    }

    private function createAggregate(string $modulePath, string $module, string $name): void
    {
        $path = "{$modulePath}/Domain/Aggregates/{$name}Aggregate.php";
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->warning("Aggregate [{$name}Aggregate] already exists. Skipping...");
            return;
        }

        $stub = $this->getAggregateStub($module, $name);
        file_put_contents($path, $stub);

        $this->io->text("  <fg=green>✓</> Created: Domain/Aggregates/{$name}Aggregate.php");
    }

    private function createExampleEvents(string $modulePath, string $module, string $name): void
    {
        $eventsDir = "{$modulePath}/Domain/Aggregates/Events";

        if (!is_dir($eventsDir)) {
            mkdir($eventsDir, 0755, true);
        }

        // Create example "Created" event
        $eventName = "{$name}Created";
        $eventPath = "{$eventsDir}/{$eventName}.php";

        if (!file_exists($eventPath)) {
            $stub = $this->getEventStub($module, $name, $eventName);
            file_put_contents($eventPath, $stub);
            $this->io->text("  <fg=green>✓</> Created: Domain/Aggregates/Events/{$eventName}.php (example)");
        }
    }

    private function getAggregateStub(string $module, string $name): string
    {
        $namespace = "Modules\\{$module}\\Domain\\Aggregates";
        $eventNamespace = "Modules\\{$module}\\Domain\\Aggregates\\Events";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\EventSourcing\AggregateRoot;
use {$eventNamespace}\\{$name}Created;

/**
 * {$name} Aggregate
 * 
 * Handles {$name} business logic and state changes through events.
 */
class {$name}Aggregate extends AggregateRoot
{
    private string \$name = '';
    private string \$status = 'pending';
    private array \$metadata = [];

    /**
     * Create a new {$name}
     */
    public function create(string \$id, string \$name, array \$metadata = []): void
    {
        // Business validation
        if (empty(\$name)) {
            throw new \InvalidArgumentException('{$name} name cannot be empty');
        }

        // Record domain event
        \$this->recordThat(new {$name}Created(
            \$id,
            \$name,
            \$metadata
        ));
    }

    /**
     * Apply {$name}Created event
     */
    protected function apply{$name}Created({$name}Created \$event): void
    {
        \$this->id = \$event->aggregateId;
        \$this->name = \$event->name;
        \$this->metadata = \$event->metadata;
        \$this->status = 'active';
    }

    // Getters
    public function getName(): string
    {
        return \$this->name;
    }

    public function getStatus(): string
    {
        return \$this->status;
    }

    public function getMetadata(): array
    {
        return \$this->metadata;
    }
}
PHP;
    }

    private function getEventStub(string $module, string $name, string $eventName): string
    {
        $namespace = "Modules\\{$module}\\Domain\\Aggregates\\Events";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\EventSourcing\DomainEvent;

/**
 * {$eventName} Event
 * 
 * Fired when a {$name} is created.
 */
class {$eventName} extends DomainEvent
{
    public function __construct(
        public readonly string \$aggregateId,
        public readonly string \$name,
        public readonly array \$metadata = []
    ) {
        parent::__construct(\$aggregateId);
    }

    public function toArray(): array
    {
        return [
            'aggregate_id' => \$this->aggregateId,
            'name' => \$this->name,
            'metadata' => \$this->metadata,
        ];
    }

    public static function fromArray(array \$data): self
    {
        return new self(
            \$data['aggregate_id'],
            \$data['name'],
            \$data['metadata'] ?? []
        );
    }
}
PHP;
    }
}
