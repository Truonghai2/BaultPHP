<?php

namespace Modules\User\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\Relations\BelongsTo;

class RoleAssignment extends Model
{
    protected static string $table = 'role_assignments';
    protected array $fillable = ['role_id', 'user_id', 'context_id'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
