<?php

namespace Modules\User\Infrastructure\Models\OAuth;

use Core\ORM\Model;

/**
 * Represents the `oauth_scopes` table.
 */
class Scope extends Model
{
    protected static string $table = 'oauth_scopes';
    protected array $fillable = ['id', 'description'];
}
