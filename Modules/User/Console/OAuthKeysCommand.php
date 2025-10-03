<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Defuse\Crypto\Key;
use Throwable;

class OAuthKeysCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'oauth:keys';
    }

    public function description(): string
    {
        return 'Create the encryption keys for OAuth2 authentication.';
    }

    public function handle(): int
    {
        $this->io->title('Generating OAuth2 Keys');

        try {
            $privateKeyPath = config('oauth2.private_key');
            $publicKeyPath = config('oauth2.public_key');

            // 1. Generate RSA key pair
            $keys = \Safe\openssl_pkey_new([
                'private_key_bits' => 4096,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            \Safe\openssl_pkey_export($keys, $privateKey);

            $publicKeyDetails = \Safe\openssl_pkey_get_details($keys);
            $publicKey = $publicKeyDetails['key'];

            \Safe\file_put_contents($privateKeyPath, $privateKey);
            \Safe\file_put_contents($publicKeyPath, $publicKey);

            \Safe\chmod($privateKeyPath, 0600);
            \Safe\chmod($publicKeyPath, 0600);

            $this->info('RSA keys generated and saved successfully.');
            $this->comment("Private key: {$privateKeyPath}");
            $this->comment("Public key: {$publicKeyPath}");

            // 2. Generate Encryption Key
            $encryptionKey = Key::createNewRandomKey()->saveToAsciiSafeString();
            $this->info('Encryption key generated successfully.');
            $this->comment('Add this to your .env file:');
            $this->line("OAUTH_ENCRYPTION_KEY='{$encryptionKey}'");
        } catch (Throwable $e) {
            $this->error("An error occurred while generating keys: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
