<?php

namespace Core\Console\Commands\Queue;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Contracts\Queue\FailedJobProviderInterface;

class QueueForgetCommand extends BaseCommand
{
    public function __construct(Application $app, protected FailedJobProviderInterface $failedJobProvider)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'queue:forget {uuid : The UUID of the failed job to forget}';
    }
    public function description(): string
    {
        return 'Delete a failed queue job from the log.';
    }
    public function handle(): int
    {
        $uuid = $this->argument('uuid');

        $deleted = $this->failedJobProvider->forget($uuid);

        if ($deleted) {
            $this->info("✔ Failed job with UUID [{$uuid}] has been forgotten (deleted from the database).");
            return 0;
        } else {
            $this->error("Failed job with UUID [{$uuid}] not found in the database.");
            return 1;
        }
    }
}
