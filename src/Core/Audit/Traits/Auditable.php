<?php

namespace Core\Audit\Traits;

use Core\Audit\Models\AuditLog;
use Core\Audit\Observers\AuditObserver;

/**
 * Auditable Trait
 *
 * Add this trait to any model to automatically log creates, updates, and deletes.
 *
 * Usage:
 * ```php
 * class User extends Model {
 *     use Auditable;
 *
 *     protected array $auditableEvents = ['created', 'updated', 'deleted'];
 *     protected array $auditableAttributes = ['name', 'email', 'role'];
 * }
 * ```
 */
trait Auditable
{
    /**
     * Boot the auditable trait by registering the observer.
     */
    public static function bootAuditable(): void
    {
        // Register the audit observer
        static::observe(AuditObserver::class);
    }

    /**
     * Check if this event should be audited.
     */
    protected function shouldAudit(string $event): bool
    {
        $events = $this->auditableEvents ?? ['created', 'updated', 'deleted'];
        return in_array($event, $events);
    }

    /**
     * Get attributes that should be audited.
     */
    protected function getAuditableAttributes(): array
    {
        if (isset($this->auditableAttributes)) {
            return $this->auditableAttributes;
        }

        // Audit all fillable attributes by default
        return $this->fillable ?? [];
    }

    /**
     * Audit model creation.
     */
    protected function auditCreated(): void
    {
        $values = $this->getAuditableValues();

        AuditLog::create([
            'event_type' => 'model.created',
            'event_category' => 'crud',
            'user_id' => auth()->id(),
            'user_type' => auth()->user() ? get_class(auth()->user()) : null,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'description' => get_class($this) . ' created',
            'new_values' => json_encode($values),
            'severity' => 'info',
        ]);
    }

    /**
     * Audit model update.
     */
    protected function auditUpdated(): void
    {
        $changes = $this->getChanges();
        $original = $this->getOriginal();

        $auditableAttrs = $this->getAuditableAttributes();

        // Filter only auditable attributes
        $oldValues = array_intersect_key($original, array_flip($auditableAttrs));
        $newValues = array_intersect_key($changes, array_flip($auditableAttrs));

        if (empty($newValues)) {
            return; // No auditable changes
        }

        AuditLog::create([
            'event_type' => 'model.updated',
            'event_category' => 'crud',
            'user_id' => auth()->id(),
            'user_type' => auth()->user() ? get_class(auth()->user()) : null,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'description' => get_class($this) . ' updated',
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'severity' => 'info',
        ]);
    }

    /**
     * Audit model deletion.
     */
    protected function auditDeleted(): void
    {
        $values = $this->getAuditableValues();

        AuditLog::create([
            'event_type' => 'model.deleted',
            'event_category' => 'crud',
            'user_id' => auth()->id(),
            'user_type' => auth()->user() ? get_class(auth()->user()) : null,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'description' => get_class($this) . ' deleted',
            'old_values' => json_encode($values),
            'severity' => 'info',
        ]);
    }

    /**
     * Get current auditable attribute values.
     */
    protected function getAuditableValues(): array
    {
        $attributes = $this->getAuditableAttributes();
        $values = [];

        foreach ($attributes as $attr) {
            if (isset($this->$attr)) {
                $values[$attr] = $this->$attr;
            }
        }

        return $values;
    }

    /**
     * Get audit logs for this model.
     */
    public function auditLogs()
    {
        return AuditLog::where('auditable_type', '=', get_class($this))
            ->where('auditable_id', '=', $this->getKey())
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
