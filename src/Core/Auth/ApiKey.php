<?php

namespace Core\Auth;

use Core\ORM\Model;

/**
 * @property int $user_id
 * @property string $name
 * @property string $key
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon|null $expires_at
 */
class ApiKey extends Model
{
    protected static string $table = 'api_keys';

    protected array $fillable = ['user_id', 'name', 'key', 'expires_at'];

    protected array $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
