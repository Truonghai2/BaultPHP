<?php

namespace Modules\User\Infrastructure\Models\OAuth;

use Core\ORM\Model;

/**
 * Represents the `oauth_refresh_tokens` table.
 */
class RefreshToken extends Model
{
    protected static string $table = 'oauth_refresh_tokens';
    public $incrementing = false;
    protected string $primaryKey = 'id';

    protected array $fillable = ['id', 'access_token_id', 'revoked', 'expires_at'];

    protected array $casts = ['revoked' => 'boolean'];
}
