<?php

namespace Modules\User\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\Relations\BelongsToMany;

class Permission extends Model
{
    /**
     * The table associated with the model.
     */
    protected static string $table = 'permissions';

    protected array $fillable = ['name', 'description', 'captype'];

    /**
     * The roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
