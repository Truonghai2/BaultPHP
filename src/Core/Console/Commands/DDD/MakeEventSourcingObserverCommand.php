<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Create Event Sourcing Observer for Eloquent Model
 */
class MakeEventSourcingObserverCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:es-observer {module : The module name} {model : The model name} {--aggregate= : Associated aggregate}';
    }

    public function description(): string
    {
        return 'Create an Event Sourcing observer for automatic event recording.';
    }

    public function handle(): int
    {
        $module = ucfirst($this->argument('module'));
        $model = ucfirst($this->argument('model'));
        $aggregate = $this->option('aggregate') ?: $model;

        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' does not exist.");
            return self::FAILURE;
        }

        $this->io->info("Creating Event Sourcing observer [{$model}EventSourcingObserver] in module [{$module}]...");

        $this->createObserver($modulePath, $module, $model, $aggregate);

        $this->io->success("Observer [{$model}EventSourcingObserver] created successfully!");
        $this->io->comment("Next steps:");
        $this->io->listing([
            "1. Register observer in EventSourcingServiceProvider",
            "2. Or add to config/event-sourcing.php aggregates configuration",
            "3. Ensure your model has the required events enabled",
            "4. Test with: Model::create([...]) to see events recorded",
        ]);

        return self::SUCCESS;
    }

    private function createObserver(string $modulePath, string $module, string $model, string $aggregate): void
    {
        $path = "{$modulePath}/Infrastructure/Observers/{$model}EventSourcingObserver.php";
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Observer [{$model}EventSourcingObserver] already exists.");
            return;
        }

        $stub = $this->getObserverStub($module, $model, $aggregate);
        file_put_contents($path, $stub);
        
        $this->io->text("  <fg=green>✓</> Created: Infrastructure/Observers/{$model}EventSourcingObserver.php");
    }

    private function getObserverStub(string $module, string $model, string $aggregate): string
    {
        $namespace = "Modules\\{$module}\\Infrastructure\\Observers";
        $modelNamespace = "Modules\\{$module}\\Infrastructure\\Models";
        $aggregateNamespace = "Modules\\{$module}\\Domain\\Aggregates";
        $eventsNamespace = "Modules\\{$module}\\Domain\\Aggregates\\Events";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$modelNamespace}\\{$model};
use Core\EventSourcing\AggregateRepository;
use {$aggregateNamespace}\\{$aggregate}Aggregate;
use {$eventsNamespace}\\{$aggregate}Created;
use {$eventsNamespace}\\{$aggregate}Updated;
use {$eventsNamespace}\\{$aggregate}Deleted;

/**
 * {$model} Event Sourcing Observer
 * 
 * Automatically records domain events when {$model} model changes.
 */
class {$model}EventSourcingObserver
{
    public function __construct(
        private AggregateRepository \$aggregateRepository
    ) {}

    /**
     * Handle the {$model} "created" event.
     */
    public function created({$model} \$model): void
    {
        if (!\$this->shouldRecord()) {
            return;
        }

        try {
            \$aggregate = new {$aggregate}Aggregate();
            
            // Record creation event
            \$aggregate->create(
                \$model->id,
                \$model->name ?? '',
                \$this->extractMetadata(\$model)
            );
            
            \$this->aggregateRepository->save(\$aggregate);
        } catch (\Exception \$e) {
            // Log error but don't break the flow
            logger()->error('Event Sourcing Observer Error: ' . \$e->getMessage(), [
                'model' => get_class(\$model),
                'id' => \$model->id,
            ]);
        }
    }

    /**
     * Handle the {$model} "updated" event.
     */
    public function updated({$model} \$model): void
    {
        if (!\$this->shouldRecord()) {
            return;
        }

        try {
            \$aggregate = \$this->aggregateRepository->load(
                {$aggregate}Aggregate::class,
                \$model->id
            );

            if (!\$aggregate) {
                // Aggregate doesn't exist yet, create it
                \$this->created(\$model);
                return;
            }

            // TODO: Add update logic
            // \$aggregate->update(...);
            
            \$this->aggregateRepository->save(\$aggregate);
        } catch (\Exception \$e) {
            logger()->error('Event Sourcing Observer Error: ' . \$e->getMessage(), [
                'model' => get_class(\$model),
                'id' => \$model->id,
            ]);
        }
    }

    /**
     * Handle the {$model} "deleted" event.
     */
    public function deleted({$model} \$model): void
    {
        if (!\$this->shouldRecord()) {
            return;
        }

        try {
            \$aggregate = \$this->aggregateRepository->load(
                {$aggregate}Aggregate::class,
                \$model->id
            );

            if (!\$aggregate) {
                return;
            }

            // TODO: Add delete logic
            // \$aggregate->delete();
            
            \$this->aggregateRepository->save(\$aggregate);
        } catch (\Exception \$e) {
            logger()->error('Event Sourcing Observer Error: ' . \$e->getMessage(), [
                'model' => get_class(\$model),
                'id' => \$model->id,
            ]);
        }
    }

    /**
     * Check if event recording should happen
     */
    private function shouldRecord(): bool
    {
        // Check global and module config
        \$globalEnabled = config('event-sourcing.enabled', false);
        \$moduleEnabled = config('event-sourcing.enabled', false);
        \$autoRecord = config('event-sourcing.auto_record.enabled', false);

        return \$globalEnabled && \$moduleEnabled && \$autoRecord;
    }

    /**
     * Extract metadata from model
     */
    private function extractMetadata({$model} \$model): array
    {
        return [
            'created_at' => \$model->created_at?->toISOString(),
            'updated_at' => \$model->updated_at?->toISOString(),
            // Add more metadata as needed
        ];
    }
}
PHP;
    }
}

