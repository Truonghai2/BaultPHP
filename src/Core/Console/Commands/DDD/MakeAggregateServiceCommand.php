<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Create Aggregate Application Service
 */
class MakeAggregateServiceCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:aggregate {module : The module name} {aggregate : The aggregate name}';
    }

    public function description(): string
    {
        return 'Create an application service for an Event Sourcing aggregate.';
    }

    public function handle(): int
    {
        $module = ucfirst($this->argument('module'));
        $aggregate = ucfirst($this->argument('aggregate'));

        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' does not exist.");
            return self::FAILURE;
        }

        $this->io->info("Creating aggregate service [{$aggregate}AggregateService] in module [{$module}]...");

        $this->createAggregateService($modulePath, $module, $aggregate);

        $this->io->success("Aggregate service [{$aggregate}AggregateService] created successfully!");
        $this->io->comment('Next steps:');
        $this->io->listing([
            "1. Inject {$aggregate}AggregateService in your controllers",
            "2. Implement use case methods (e.g., create{$aggregate}, update{$aggregate})",
            '3. Use AggregateRepository to load/save aggregates',
            '4. Optionally create a DomainService for complex business logic',
        ]);

        return self::SUCCESS;
    }

    private function createAggregateService(string $modulePath, string $module, string $aggregate): void
    {
        $path = "{$modulePath}/Application/Services/{$aggregate}AggregateService.php";
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Aggregate service [{$aggregate}AggregateService] already exists.");
            return;
        }

        $stub = $this->getServiceStub($module, $aggregate);
        file_put_contents($path, $stub);

        $this->io->text("  <fg=green>✓</> Created: Application/Services/{$aggregate}AggregateService.php");
    }

    private function getServiceStub(string $module, string $aggregate): string
    {
        $namespace = "Modules\\{$module}\\Application\\Services";
        $aggregateNamespace = "Modules\\{$module}\\Domain\\Aggregates";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\EventSourcing\AggregateRepository;
use {$aggregateNamespace}\\{$aggregate}Aggregate;
use Ramsey\Uuid\Uuid;

/**
 * {$aggregate} Aggregate Service
 * 
 * Application service for {$aggregate} aggregate.
 * Orchestrates use cases and coordinates infrastructure.
 */
class {$aggregate}AggregateService
{
    public function __construct(
        private AggregateRepository \$aggregateRepository
    ) {}

    /**
     * Create a new {$aggregate}
     */
    public function create{$aggregate}(string \$name, array \$metadata = []): string
    {
        \$id = Uuid::uuid4()->toString();
        
        // Create new aggregate
        \${strtolower($aggregate)} = new {$aggregate}Aggregate();
        \${strtolower($aggregate)}->create(\$id, \$name, \$metadata);
        
        // Save to event store
        \$this->aggregateRepository->save(\${strtolower($aggregate)});
        
        return \$id;
    }

    /**
     * Load {$aggregate} by ID
     */
    public function load{$aggregate}(string \$id): ?{$aggregate}Aggregate
    {
        return \$this->aggregateRepository->load(
            {$aggregate}Aggregate::class,
            \$id
        );
    }

    /**
     * Get {$aggregate} current state
     */
    public function get{$aggregate}State(string \$id): ?array
    {
        \${strtolower($aggregate)} = \$this->load{$aggregate}(\$id);
        
        if (!\${strtolower($aggregate)}) {
            return null;
        }

        return [
            'id' => \${strtolower($aggregate)}->getId(),
            'name' => \${strtolower($aggregate)}->getName(),
            'status' => \${strtolower($aggregate)}->getStatus(),
            'metadata' => \${strtolower($aggregate)}->getMetadata(),
            'version' => \${strtolower($aggregate)}->getVersion(),
        ];
    }

    // TODO: Add more use case methods here
    // Example:
    // public function update{$aggregate}Name(string \$id, string \$newName): void
    // {
    //     \${strtolower($aggregate)} = \$this->load{$aggregate}(\$id);
    //     if (!\${strtolower($aggregate)}) {
    //         throw new \DomainException('{$aggregate} not found');
    //     }
    //     
    //     \${strtolower($aggregate)}->updateName(\$newName);
    //     \$this->aggregateRepository->save(\${strtolower($aggregate)});
    // }
}
PHP;
    }
}
