<?php

namespace Modules\User\Infrastructure\Models\OAuth;

use Core\ORM\Model;

/**
 * Represents the `oauth_access_tokens` table.
 */
class AccessToken extends Model
{
    protected static string $table = 'oauth_access_tokens';
    public $incrementing = false;
    protected string $primaryKey = 'id';

    protected array $fillable = ['id', 'user_id', 'client_id', 'scopes', 'revoked', 'created_at', 'updated_at', 'expires_at'];

    protected array $casts = ['revoked' => 'boolean'];
}
