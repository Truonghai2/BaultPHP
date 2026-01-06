<?php

namespace Modules\User\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;
use Core\ORM\Relations\BelongsToMany;

/**
 * Role Model
 * 
 * Represents a user role with associated permissions.
 * Auto-logs all role changes for security audit.
 * 
 * @property int $id
 * @property string $name
 * @property string $description
 * @property-read \Core\Support\Collection<int, \Modules\User\Infrastructure\Models\User> $users
 * @property-read \Core\Support\Collection<int, \Modules\User\Infrastructure\Models\Permission> $permissions
 */
class Role extends Model
{
    use Auditable;

    /**
     * The table associated with the model.
     */
    protected static string $table = 'roles';

    protected array $fillable = ['name', 'description'];

    /**
     * Define which events should be audited.
     */
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    /**
     * Define which attributes should be tracked in audit logs.
     */
    protected array $auditableAttributes = ['name', 'description'];

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    /**
     * The users that are assigned this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_assignments');
    }
}
