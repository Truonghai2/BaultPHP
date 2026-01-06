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
        'id', 'user_id', 'name', 'secret', 'provider', 'redirect', 'redirect_uri', 'is_personal_access_client', 'is_password_client', 'is_revoked',
    ];

    protected array $casts = [
        'is_personal_access_client' => 'boolean',
        'is_password_client' => 'boolean',
        'is_revoked' => 'boolean',
    ];
}
