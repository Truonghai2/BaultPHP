<?php

namespace Core\Search\Jobs;

use Core\Contracts\Queue\Job;
use Core\ORM\Model;
use InvalidArgumentException;
use MeiliSearch\Client;

class BulkIndexJob implements Job
{
    /**
     * Create a new job instance.
     *
     * @param string $searchableClass The class name of the models to index.
     * @param array $searchableIds An array of model IDs to index.
     */
    public function __construct(
        public string $searchableClass,
        public array $searchableIds,
    ) {
    }

    /**
     * Execute the job.
     *
     * This job is designed to be robust. It validates the model class and
     * processes records in chunks to prevent memory issues with large datasets.
     *
     * @param \MeiliSearch\Client $client
     * @return void
     * @throws \InvalidArgumentException
     */
    public function handle(Client $client): void
    {
        if (empty($this->searchableIds)) {
            return;
        }

        $modelClass = $this->searchableClass;

        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("The provided class '{$modelClass}' is not a valid searchable model.");
        }

        $indexName = $modelClass::getSearchIndexName();
        $primaryKey = $modelClass::getPrimaryKeyName();

        // Process IDs in chunks to avoid memory exhaustion and overly long SQL queries.
        foreach (array_chunk($this->searchableIds, 500) as $chunkOfIds) {
            $models = $modelClass::whereIn($primaryKey, $chunkOfIds)->get();

            if ($models->isEmpty()) {
                continue;
            }

            // Transform models into a format suitable for Meilisearch.
            $documents = $models->map(fn (Model $model) => $model->toSearchableArray())->all();

            if (empty($documents)) {
                continue;
            }

            // Send the documents to Meilisearch for indexing.
            $client->index($indexName)->addDocuments($documents);
        }
    }
}
