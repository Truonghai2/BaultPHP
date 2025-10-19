<?php

namespace Core\Auth;

use Core\ORM\Model;
use Modules\User\Infrastructure\Models\User;

/**
 * Represents a "remember me" token in the database.
 *
 * @property int $user_id
 * @property string $selector
 * @property string $verifier_hash
 */
class RememberToken extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected static string $table = 'remember_tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'user_id',
        'selector',
        'verifier_hash',
        'user_agent',
        'ip_address',
        'expires_at',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = true;

    protected array $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that the token belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
