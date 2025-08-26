<?php

namespace Modules\User\Infrastructure\Models\OAuth;

use Core\ORM\Model;

/**
 * Represents the `oauth_auth_codes` table.
 */
class AuthCode extends Model
{
    protected static string $table = 'oauth_auth_codes';
    public $incrementing = false;
    protected string $primaryKey = 'id';

    protected array $fillable = ['id', 'user_id', 'client_id', 'scopes', 'revoked', 'expires_at'];
    protected array $casts = ['revoked' => 'boolean'];
}
