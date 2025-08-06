<?php

namespace Core\Search\Jobs;

use Core\Contracts\Queue\Job;
use MeiliSearch\Client;

class RemoveFromSearchJob implements Job
{
    /**
     * Create a new job instance.
     *
     * @param string $indexName The name of the Meilisearch index.
     * @param mixed $documentId The ID of the document to remove.
     */
    public function __construct(
        public string $indexName,
        public mixed $documentId,
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
        $client->index($this->indexName)->deleteDocument($this->documentId);
    }
}
