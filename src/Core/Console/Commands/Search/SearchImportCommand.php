<?php

namespace Core\Console\Commands\Search;

use Core\Console\Contracts\BaseCommand;
use Core\ORM\Model;
use Core\Search\Jobs\BulkIndexJob;
use Core\Search\Searchable;
use Throwable;

class SearchImportCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'search:import 
                {model : The model class to import, e.g., "Modules\Post\Infrastructure\Models\Post"}
                {--chunk=500 : The number of records to import at a time}';
    }

    public function description(): string
    {
        return 'Import the data from a model into the Meilisearch index.';
    }

    public function handle(): int
    {
        $modelClass = $this->argument('model');
        $chunkSize = (int) $this->option('chunk');

        if (!class_exists($modelClass)) {
            $this->io->error("Model class [{$modelClass}] not found.");
            return self::FAILURE;
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            $this->io->error("Class [{$modelClass}] is not a valid Model.");
            return self::FAILURE;
        }

        $classUses = class_uses_recursive($modelClass);
        if (!in_array(Searchable::class, $classUses)) {
            $this->io->error("Model [{$modelClass}] does not use the Searchable trait.");
            return self::FAILURE;
        }

        $this->io->title("Importing [{$modelClass}] into Meilisearch");

        try {
            /** @var Model $modelInstance */
            $modelInstance = new $modelClass();
            $query = $modelInstance::query();
            $count = $query->count();

            if ($count === 0) {
                $this->io->info("No records found for [{$modelClass}]. Nothing to import.");
                return self::SUCCESS;
            }

            $this->io->writeln("Found <info>{$count}</info> records to import.");
            $progressBar = $this->io->createProgressBar($count);
            $progressBar->start();

            $query->chunkById($chunkSize, function ($models) use ($modelClass, $progressBar) {
                $keys = $models->map(fn ($model) => $model->getKey())->all();
                BulkIndexJob::dispatch($modelClass, $keys);
                $progressBar->advance(count($models));
            });

            $progressBar->finish();
            $this->io->newLine(2);
            $this->io->success("Successfully dispatched all records for [{$modelClass}] to the queue for indexing.");
            $this->io->comment('Run `php cli queue:work` to process the jobs.');
        } catch (Throwable $e) {
            $this->io->error('An error occurred: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
