<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Core\ORM\Connection;
use Core\Schema\Schema as DbSchema;
use Modules\User\Infrastructure\Models\OAuth\Client;

class OAuthClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {   
        /** @var \PDO $pdo */
        $pdo = $this->container->make(Connection::class)->connection();
        $schema = new DbSchema($pdo);

        $redirectColumn = $schema->hasColumn('oauth_clients', 'redirect') ? 'redirect' : 'redirect_uri';

        $webAppSecret = env('OAUTH_WEB_CLIENT_SECRET') ?: bin2hex(random_bytes(32));
        $mobileAppSecret = env('OAUTH_MOBILE_CLIENT_SECRET') ?: bin2hex(random_bytes(32));

        $webClient = Client::firstOrCreate(
            ['id' => '9c882444-76cf-4423-9216-a0a1f485b132'],
            [
                'name' => 'My Web App',
                'secret' => \Core\Support\Facades\Hash::make($webAppSecret),
                $redirectColumn => env('OAUTH_WEB_CLIENT_REDIRECT', 'http://localhost:3000/callback'),
                'is_personal_access_client' => false,
                'is_password_client' => false,
                'is_revoked' => false,
            ],
        );

        $mobileClient = Client::firstOrCreate(
            ['id' => '9c8824a7-85b4-431c-991b-3a5a101f7a2c'],
            [
                'name' => 'My Mobile App',
                'secret' => \Core\Support\Facades\Hash::make($mobileAppSecret),
                $redirectColumn => 'http://localhost',
                'is_personal_access_client' => false,
                'is_password_client' => true,
                'is_revoked' => false,
            ],
        );

        $this->command->info('OAuth clients created successfully.');
        $this->command->line('  <fg=yellow>Web App (Auth Code) Client ID:</> 9c882444-76cf-4423-9216-a0a1f485b132');
        
        if ($webClient && isset($webClient->wasRecentlyCreated) && $webClient->wasRecentlyCreated && !env('OAUTH_WEB_CLIENT_SECRET')) {
            $this->command->line('  <fg=red>Web App Client Secret:</> ' . $webAppSecret);
            $this->command->warn('Save this secret! Add to .env: OAUTH_WEB_CLIENT_SECRET=' . $webAppSecret);
        }
        
        $this->command->line('  <fg=yellow>Mobile App (Password) Client ID:</> 9c8824a7-85b4-431c-991b-3a5a101f7a2c');
        
        if ($mobileClient && isset($mobileClient->wasRecentlyCreated) && $mobileClient->wasRecentlyCreated && !env('OAUTH_MOBILE_CLIENT_SECRET')) {
            $this->command->line('  <fg=red>Mobile App Client Secret:</> ' . $mobileAppSecret);
            $this->command->warn('Save this secret! Add to .env: OAUTH_MOBILE_CLIENT_SECRET=' . $mobileAppSecret);
        }
    }
}
