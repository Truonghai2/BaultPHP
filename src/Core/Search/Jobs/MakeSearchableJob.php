<?php

namespace Core\Search\Jobs;

use Core\Contracts\Queue\ShouldQueue;
use Core\ORM\Model;
use MeiliSearch\Client;
use Psr\Log\LoggerInterface;

class MakeSearchableJob implements ShouldQueue
{
    /**
     * @param class-string<Model> $modelClass
     * @param int|string $modelId
     */
    public function __construct(
        private string $modelClass,
        private int|string $modelId,
    ) {
    }

    public function handle(Client $meilisearch, LoggerInterface $logger): void
    {
        /** @var \Core\Search\Searchable|Model|null $model */
        $model = $this->modelClass::find($this->modelId);

        if (!$model) {
            $logger->warning('Searchable model not found, skipping indexing.', [
                'model' => $this->modelClass,
                'id' => $this->modelId,
            ]);
            return;
        }

        $indexName = $model::getSearchIndexName();
        $data = $model->toSearchableArray();

        // Meilisearch yêu cầu phải có primary key.
        $primaryKey = $model->getKeyName();
        if (!isset($data[$primaryKey])) {
            $data[$primaryKey] = $model->getKey();
        }

        $meilisearch->index($indexName)->addDocuments([$data], $primaryKey);
    }
}
