<?php

namespace Core\Audit\Observers;

use Core\Audit\Models\AuditLog;
use Core\ORM\Model;

/**
 * AuditObserver
 * 
 * Observes model events and creates audit logs.
 */
class AuditObserver
{
    /**
     * Handle the model "created" event.
     */
    public function created(Model $model): void
    {
        if (!$this->shouldAudit($model, 'created')) {
            return;
        }

        $values = $this->getAuditableValues($model);

        $this->createAuditLog([
            'event_type' => 'model.created',
            'event_category' => 'data_change',
            'user_id' => $this->getUserId(),
            'user_type' => $this->getUserType(),
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'description' => $this->getModelName($model) . ' created',
            'new_values' => json_encode($values),
            'severity' => 'info',
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
        ]);
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        if (!$this->shouldAudit($model, 'updated')) {
            return;
        }

        $changes = $model->getDirty();
        $original = $model->getOriginal();
        
        $auditableAttrs = $this->getAuditableAttributes($model);
        
        // Filter only auditable attributes
        if (!empty($auditableAttrs)) {
            $oldValues = array_intersect_key($original, array_flip($auditableAttrs));
            $newValues = array_intersect_key($changes, array_flip($auditableAttrs));
        } else {
            $oldValues = $original;
            $newValues = $changes;
        }

        if (empty($newValues)) {
            return; // No auditable changes
        }

        $this->createAuditLog([
            'event_type' => 'model.updated',
            'event_category' => 'data_change',
            'user_id' => $this->getUserId(),
            'user_type' => $this->getUserType(),
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'description' => $this->getModelName($model) . ' updated',
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'severity' => 'info',
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
        ]);
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        if (!$this->shouldAudit($model, 'deleted')) {
            return;
        }

        $values = $this->getAuditableValues($model);

        $this->createAuditLog([
            'event_type' => 'model.deleted',
            'event_category' => 'data_change',
            'user_id' => $this->getUserId(),
            'user_type' => $this->getUserType(),
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'description' => $this->getModelName($model) . ' deleted',
            'old_values' => json_encode($values),
            'severity' => 'warning',
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
        ]);
    }

    /**
     * Check if this event should be audited.
     */
    protected function shouldAudit(Model $model, string $event): bool
    {
        // Check if model has auditableEvents property
        if (property_exists($model, 'auditableEvents')) {
            return in_array($event, $model->auditableEvents);
        }

        // Default: audit all events (created, updated, deleted)
        return in_array($event, ['created', 'updated', 'deleted']);
    }

    /**
     * Get attributes that should be audited.
     */
    protected function getAuditableAttributes(Model $model): array
    {
        if (property_exists($model, 'auditableAttributes')) {
            return $model->auditableAttributes;
        }

        // If no specific attributes, return empty to audit all
        return [];
    }

    /**
     * Get current auditable attribute values.
     */
    protected function getAuditableValues(Model $model): array
    {
        $attributes = $this->getAuditableAttributes($model);
        
        if (empty($attributes)) {
            // Return all attributes
            return $model->getAttributes();
        }

        $values = [];
        foreach ($attributes as $attr) {
            if (isset($model->$attr)) {
                $values[$attr] = $model->$attr;
            }
        }

        return $values;
    }

    /**
     * Get the current user ID.
     */
    protected function getUserId(): ?int
    {
        try {
            return auth()->id();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the current user type.
     */
    protected function getUserType(): ?string
    {
        try {
            $user = auth()->user();
            return $user ? get_class($user) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get IP address from request.
     */
    protected function getIpAddress(): ?string
    {
        try {
            $request = request();
            return $request ? $request->getAttribute('client_ip') ?? $request->getServerParams()['REMOTE_ADDR'] ?? null : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user agent from request.
     */
    protected function getUserAgent(): ?string
    {
        try {
            $request = request();
            return $request ? $request->getHeaderLine('User-Agent') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get a friendly model name.
     */
    protected function getModelName(Model $model): string
    {
        $className = get_class($model);
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Create an audit log entry.
     */
    protected function createAuditLog(array $data): void
    {
        try {
            AuditLog::create($data);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}

