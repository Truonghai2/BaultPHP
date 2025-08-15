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
    private Client $client;
    private LoggerInterface $logger;

    // Properties to be serialized and stored in the queue payload.
    protected string $indexName;
    protected array $documents;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param string $indexName The name of the index to update.
     * @param array $documents The documents to be indexed.
     */
    public function __construct(string $indexName, array $documents)
    {
        $this->indexName = $indexName;
        $this->documents = $documents;
    }

    /**
     * Execute the job. This method is called by the queue worker.
     */
    public function handle(): void
    {
        // Resolve dependencies inside handle() for maximum safety in long-running workers.
        $this->client = app(Client::class);
        $this->logger = app(LoggerInterface::class);

        $this->logger->info("Starting bulk index job for index '{$this->indexName}'.", ['docs_count' => count($this->documents)]);
        $this->client->index($this->indexName)->addDocuments($this->documents);
        $this->logger->info("Finished bulk index job for index '{$this->indexName}'.");
    }

    /**
     * Handle a job failure. This overrides the default behavior in BaseJob.
     *
     * @param  \Throwable|null  $e
     */
    public function fail(Throwable $e = null): void
    {
        // Re-resolve logger in case it wasn't resolved in handle() due to an early error.
        $this->logger = app(LoggerInterface::class);

        if ($e) {
            $this->logger->error(
                "BulkIndexJob failed for index '{$this->indexName}': " . $e->getMessage(),
                ['exception' => $e],
            );
        } else {
            $this->logger->error("BulkIndexJob failed for index '{$this->indexName}' for an unknown reason.");
        }
    }

    /**
     * Prepare the data that should be serialized for the queue.
     */
    public function __serialize(): array
    {
        return [
            'indexName' => $this->indexName,
            'documents' => $this->documents,
        ];
    }

    /**
     * Restore the object's state after being unserialized from the queue.
     */
    public function __unserialize(array $data): void
    {
        $this->indexName = $data['indexName'];
        $this->documents = $data['documents'];
    }
}
