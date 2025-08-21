<?php

namespace Core\Search\Jobs;

use Core\Contracts\Queue\ShouldQueue;
use MeiliSearch\Client;
use Psr\Log\LoggerInterface;

class RemoveFromSearchJob implements ShouldQueue
{
    public function __construct(
        private string $indexName,
        private int|string $documentId,
    ) {
    }

    public function handle(Client $meilisearch, LoggerInterface $logger): void
    {
        $meilisearch->index($this->indexName)->deleteDocument($this->documentId);

        $logger->info('Document removed from Meilisearch index.', ['index' => $this->indexName, 'id' => $this->documentId]);
    }
}
