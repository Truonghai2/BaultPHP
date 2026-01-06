<?php

namespace Modules\User\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;
use Core\ORM\Relations\BelongsToMany;

/**
 * Permission Model
 * 
 * Represents a system permission that can be assigned to roles.
 * Auto-logs all permission changes for security compliance.
 * 
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $captype
 */
class Permission extends Model
{
    use Auditable;

    /**
     * The table associated with the model.
     */
    protected static string $table = 'permissions';

    protected array $fillable = ['name', 'description', 'captype'];

    /**
     * Define which events should be audited.
     */
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    /**
     * Define which attributes should be tracked in audit logs.
     */
    protected array $auditableAttributes = ['name', 'description', 'captype'];

    /**
     * The roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
