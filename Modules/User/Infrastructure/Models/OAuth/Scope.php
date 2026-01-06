<?php

namespace Modules\User\Infrastructure\Models\OAuth;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;

/**
 * OAuth Scope Model
 *
 * Represents available OAuth scopes for API access control.
 * Auto-logs all scope changes for security compliance.
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property int $priority
 */
class Scope extends Model
{
    use Auditable;

    protected static string $table = 'oauth_scopes';

    protected static string $primaryKey = 'id';

    public bool $incrementing = false;

    protected string $keyType = 'string';

    protected array $fillable = [
        'id',
        'name',
        'description',
        'is_default',
        'priority',
    ];

    protected array $casts = [
        'is_default' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Define which events should be audited.
     */
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    /**
     * Define which attributes should be tracked in audit logs.
     */
    protected array $auditableAttributes = ['name', 'description', 'is_default', 'priority'];

    /**
     * Get all default scopes.
     */
    public static function defaults(): array
    {
        return static::where('is_default', '=', true)
            ->orderBy('priority', 'asc')
            ->get()
            ->all();
    }

    /**
     * Get scopes by identifiers.
     */
    public static function findByIdentifiers(array $identifiers): array
    {
        return static::whereIn('id', $identifiers)
            ->orderBy('priority', 'asc')
            ->get()
            ->all();
    }
}
