<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\User\Infrastructure\Models\OAuth\Client;

class OAuthClientRevokeCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'oauth:client:revoke {client : The client ID to revoke}';
    }

    public function description(): string
    {
        return 'Revoke an OAuth2 client';
    }

    public function handle(): int
    {
        $clientId = $this->argument('client');

        $client = Client::find($clientId);

        if (!$client) {
            $this->io->error("Client with ID '{$clientId}' not found.");
            return self::FAILURE;
        }

        if ($client->revoked) {
            $this->io->warning("Client '{$client->name}' is already revoked.");
            return self::SUCCESS;
        }

        $confirmed = $this->io->confirm(
            "Are you sure you want to revoke client '{$client->name}'?",
            false
        );

        if (!$confirmed) {
            $this->io->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $client->revoked = true;
        $client->save();

        $this->io->success("Client '{$client->name}' has been revoked successfully.");

        return self::SUCCESS;
    }
}

