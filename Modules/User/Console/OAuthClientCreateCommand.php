<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Support\Facades\Hash;
use Modules\User\Infrastructure\Models\OAuth\Client;
use Ramsey\Uuid\Uuid;

class OAuthClientCreateCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'oauth:client 
                {--name= : The name of the client}
                {--redirect= : The redirect URI}
                {--public : Create a public client (no secret)}
                {--password : Create a password grant client}
                {--personal : Create a personal access client}';
    }

    public function description(): string
    {
        return 'Create a new OAuth2 client';
    }

    public function handle(): int
    {
        $name = $this->option('name');
        $redirect = $this->option('redirect');
        $isPublic = $this->option('public');
        $isPassword = $this->option('password');
        $isPersonal = $this->option('personal');

        // Prompt for name if not provided
        if (!$name) {
            $name = $this->io->ask('What is the name of the client?', 'My Application');
        }

        // Prompt for redirect if not provided
        if (!$redirect) {
            $redirect = $this->io->ask('What is the redirect URI?', 'http://localhost');
        }

        // Generate client ID
        $clientId = Uuid::uuid4()->toString();

        // Generate secret (if confidential client)
        $secret = null;
        $plainSecret = null;
        if (!$isPublic) {
            $plainSecret = bin2hex(random_bytes(32));
            $secret = Hash::make($plainSecret);
        }

        // Create the client
        $client = Client::create([
            'id' => $clientId,
            'name' => $name,
            'secret' => $secret,
            'redirect' => $redirect,
            'personal_access_client' => $isPersonal,
            'password_client' => $isPassword,
            'revoked' => false,
        ]);

        $this->io->success('OAuth client created successfully!');
        $this->io->newLine();
        
        $this->io->table(
            ['Key', 'Value'],
            [
                ['Client ID', $clientId],
                ['Client Name', $name],
                ['Redirect URI', $redirect],
                ['Type', $isPublic ? 'Public' : 'Confidential'],
                ['Password Grant', $isPassword ? 'Yes' : 'No'],
                ['Personal Access', $isPersonal ? 'Yes' : 'No'],
            ]
        );

        if ($plainSecret) {
            $this->io->newLine();
            $this->io->warning('Make sure to copy your client secret now. You won\'t be able to see it again!');
            $this->io->writeln("<fg=red>Client Secret:</> {$plainSecret}");
        }

        return self::SUCCESS;
    }
}

