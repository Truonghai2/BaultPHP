<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;
use Core\Support\Facades\Hash;
use Modules\User\Infrastructure\Models\OAuth\Client;

return new class () extends Migration {
    public function up(): void
    {
        // Hash all existing plain-text client secrets
        $clients = Client::whereNotNull('secret')->get();

        $hashedCount = 0;
        foreach ($clients as $client) {
            // Only hash if not already hashed (bcrypt hashes start with $2y$)
            if ($client->secret && !str_starts_with($client->secret, '$2y$')) {
                $client->secret = Hash::make($client->secret);
                $client->save();
                $hashedCount++;
            }
        }

        echo "Hashed {$hashedCount} client secrets.\n";
    }

    public function down(): void
    {
        echo "Cannot reverse password hashing. Client secrets remain hashed.\n";
    }
};

