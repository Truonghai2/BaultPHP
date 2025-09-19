<?php

namespace Core\Search\Jobs;

use Core\Queue\BaseJob;
use Core\Queue\Dispatchable;
use MeiliSearch\Client;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles the asynchronous bulk indexing of documents into a MeiliSearch index.
 * Dependencies are injected via the constructor by the DI container.
 */
class BulkIndexJob extends BaseJob
{
    use Dispatchable;
    // Dependencies are not serialized. They are resolved at runtime.

    // Properties to be serialized and stored in the queue payload.
    protected string $modelClass;
    protected array $modelKeys;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance. The job only stores the model class and keys
     * to keep the payload small and avoid serialization issues with complex objects.
     *
     * @param string $modelClass The fully qualified class name of the model.
     * @param array $modelKeys The primary keys of the models to be indexed.
     */
    public function __construct(string $modelClass, array $modelKeys)
    {
        $this->modelClass = $modelClass;
        $this->modelKeys = $modelKeys;
    }

    /**
     * Execute the job. This method is called by the queue worker.
     * Dependencies are automatically injected by the container thanks to the change in QueueWorker.
     *
     * @param Client $client The MeiliSearch client instance.
     * @param LoggerInterface $logger The logger instance.
     */
    public function handle(Client $client, LoggerInterface $logger): void
    {
        if (!class_exists($this->modelClass) || !is_subclass_of($this->modelClass, \Core\ORM\Model::class)) {
            $logger->error("Invalid model class '{$this->modelClass}' in BulkIndexJob.");
            $this->delete();
            return;
        }

        $models = $this->modelClass::findMany($this->modelKeys);

        if ($models->isEmpty()) {
            $logger->info("No models found for keys in BulkIndexJob for model '{$this->modelClass}'. Nothing to index.");
            return;
        }

        // The toSearchableArray() method should be defined in the Searchable trait.
        $documents = $models->map(fn ($model) => $model->toSearchableArray())->all();
        $indexName = (new $this->modelClass())->searchableAs();

        $logger->info("Starting bulk index job for index '{$indexName}'.", ['docs_count' => count($documents)]);
        $client->index($indexName)->addDocuments($documents);
        $logger->info("Finished bulk index job for index '{$indexName}'.");
    }

    /**
     * Handle a job failure. This overrides the default behavior in BaseJob.
     *
     * @param  \Throwable|null  $e
     * @param  LoggerInterface $logger
     */
    public function fail(Throwable $e = null, LoggerInterface $logger = null): void
    {
        parent::fail($e);
    }

    /**
     * Prepare the data that should be serialized for the queue.
     */
    public function __serialize(): array
    {
        return [
            'modelClass' => $this->modelClass,
            'modelKeys' => $this->modelKeys,
        ];
    }

    /**
     * Restore the object's state after being unserialized from the queue.
     */
    public function __unserialize(array $data): void
    {
        $this->modelClass = $data['modelClass'];
        $this->modelKeys = $data['modelKeys'];
    }
}
