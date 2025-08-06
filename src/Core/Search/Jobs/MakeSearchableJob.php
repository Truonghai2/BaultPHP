<?php

namespace Core\Search\Jobs;

use Core\Contracts\Queue\Job;
use MeiliSearch\Client;

class MakeSearchableJob implements Job
{
    /**
     * Create a new job instance.
     *
     * @param string $searchableClass The class name of the model to index.
     * @param mixed $searchableId The ID of the model to index.
     */
    public function __construct(
        public string $searchableClass,
        public mixed $searchableId,
    ) {
    }

    /**
     * Execute the job.
     *
     * @param \MeiliSearch\Client $client
     * @return void
     */
    public function handle(Client $client): void
    {
        $model = ($this->searchableClass)::find($this->searchableId);

        // Đảm bảo model tồn tại và có các phương thức cần thiết từ trait Searchable
        if ($model && method_exists($model, 'getSearchIndexName') && method_exists($model, 'toSearchableArray')) {
            $client->index($model->getSearchIndexName())->addDocuments([$model->toSearchableArray()]);
        }
    }
}
