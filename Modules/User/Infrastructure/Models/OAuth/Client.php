<?php

namespace Modules\User\Infrastructure\Models\OAuth;

use Core\ORM\Model;

/**
 * Represents the `oauth_clients` table.
 */
class Client extends Model
{
    protected static string $table = 'oauth_clients';

    protected array $fillable = [
        'id', 'user_id', 'name', 'secret', 'provider', 'redirect', 'personal_access_client', 'password_client', 'revoked',
    ];

    protected array $casts = [
        'personal_access_client' => 'boolean',
        'password_client' => 'boolean',
        'revoked' => 'boolean',
    ];
}
