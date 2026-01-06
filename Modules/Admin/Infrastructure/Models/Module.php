<?php

namespace Modules\Admin\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;

/**
 * Represents a module in the system, mapping to the 'modules' table.
 *
 * @property int $id
 * @property string $name
 * @property string $version
 * @property bool $enabled
 * @property string $status
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Module extends Model
{
    use Auditable;

    protected static string $table = 'modules';

    protected array $fillable = ['name', 'version', 'enabled', 'status', 'description'];

    protected $casts = ['enabled' => 'boolean'];

    /**
     * Define which events should be audited.
     */
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    /**
     * Define which attributes should be tracked in audit logs.
     */
    protected array $auditableAttributes = ['name', 'version', 'enabled', 'status', 'description'];
}
