<?php

namespace Core\Console\Commands\Database;

use Core\Console\Contracts\BaseCommand;

class CheckConnectionCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'db:check';
    }

    public function description(): string
    {
        return 'Checks if a connection to the database can be established.';
    }

    public function handle(): int
    {
        try {
            // Use the application's DB manager to attempt a connection.
            $this->app->make('db')->connection()->getPdo();
            $this->io->success('<info>Database connection successful.</info>');
            return self::SUCCESS;
        } catch (\Exception $e) {
            return self::FAILURE;
        }
    }
}
