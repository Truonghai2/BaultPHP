<?php

namespace Modules\User\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;
use Core\ORM\Relations\BelongsTo;

/**
 * RoleAssignment Model
 *
 * Represents the assignment of a role to a user in a specific context.
 * Critical for security - all role assignments are logged.
 *
 * @property int $id
 * @property int $role_id
 * @property int $user_id
 * @property int|null $context_id
 */
class RoleAssignment extends Model
{
    use Auditable;

    protected static string $table = 'role_assignments';
    protected array $fillable = ['role_id', 'user_id', 'context_id'];

    /**
     * Define which events should be audited.
     * Role assignments are critical for security.
     */
    protected array $auditableEvents = ['created', 'deleted'];

    /**
     * Define which attributes should be tracked in audit logs.
     */
    protected array $auditableAttributes = ['role_id', 'user_id', 'context_id'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
