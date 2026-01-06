<?php

namespace Core\Module;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;

/**
 * ModuleSettings Model
 *
 * Stores module configuration that can be changed at runtime.
 * Auto-logs all configuration changes for audit trail.
 *
 * @property int $id
 * @property string $module_name
 * @property string $key
 * @property mixed $value
 * @property string $type
 * @property string|null $description
 * @property string|null $group
 * @property bool $is_public
 * @property bool $is_encrypted
 * @property int $order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ModuleSettings extends Model
{
    use Auditable;

    protected static string $table = 'module_settings';

    protected $fillable = [
        'module_name',
        'key',
        'value',
        'type',
        'description',
        'group',
        'is_public',
        'is_encrypted',
        'order',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_encrypted' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Define which events should be audited.
     */
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    /**
     * Define which attributes should be tracked in audit logs.
     * Critical settings changes will be logged with old and new values.
     */
    protected array $auditableAttributes = ['key', 'value', 'type', 'module_name'];

    /**
     * Get the actual value with proper type casting.
     */
    public function getCastedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float', 'double' => (float) $this->value,
            'json' => json_decode($this->value, true),
            'array' => unserialize($this->value),
            default => $this->value,
        };
    }

    /**
     * Set value with proper type encoding.
     */
    public function setCastedValue(mixed $value): void
    {
        $this->value = match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'integer', 'float', 'double' => (string) $value,
            'json' => json_encode($value),
            'array' => serialize($value),
            default => (string) $value,
        };
    }

    /**
     * Scope: Get settings for a specific module.
     */
    public function scopeForModule($query, string $moduleName)
    {
        return $query->where('module_name', $moduleName);
    }

    /**
     * Scope: Get settings in a specific group.
     */
    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope: Get public settings only.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
