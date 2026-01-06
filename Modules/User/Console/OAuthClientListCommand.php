<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\User\Infrastructure\Models\OAuth\Client;

class OAuthClientListCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'oauth:clients {--revoked : Show revoked clients}';
    }

    public function description(): string
    {
        return 'List all OAuth2 clients';
    }

    public function handle(): int
    {
        $showRevoked = $this->option('revoked');

        $query = Client::query();

        if (!$showRevoked) {
            $query->where('revoked', '=', false);
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            $this->io->warning('No OAuth clients found.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                $client->id,
                $client->name,
                $client->redirect,
                $client->secret ? 'Confidential' : 'Public',
                $client->password_client ? 'Yes' : 'No',
                $client->personal_access_client ? 'Yes' : 'No',
                $client->revoked ? 'Yes' : 'No',
            ];
        }

        $this->io->table(
            ['Client ID', 'Name', 'Redirect', 'Type', 'Password', 'Personal', 'Revoked'],
            $rows,
        );

        $this->io->writeln("\nTotal clients: " . count($rows));

        return self::SUCCESS;
    }
}
