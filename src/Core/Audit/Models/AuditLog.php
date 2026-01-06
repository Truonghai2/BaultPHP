<?php

namespace Core\Audit\Models;

use Core\ORM\Model;
use Modules\User\Infrastructure\Models\User;

/**
 * Audit Log Model
 *
 * Stores all system audit trails including:
 * - OAuth events (token issued, revoked, failed)
 * - Authentication events (login, logout, failed attempts)
 * - Model changes (created, updated, deleted)
 * - System events (configuration changes, etc.)
 */
class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    protected array $fillable = [
        'event_type',
        'event_category',
        'user_id',
        'user_type',
        'ip_address',
        'user_agent',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'severity',
        'is_sensitive',
    ];

    protected array $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'is_sensitive' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action.
     */
    public function user()
    {
        if (!$this->user_id) {
            return null;
        }

        return User::find($this->user_id);
    }

    /**
     * Get the auditable model.
     */
    public function auditable()
    {
        if (!$this->auditable_type || !$this->auditable_id) {
            return null;
        }

        $class = $this->auditable_type;
        return $class::find($this->auditable_id);
    }

    /**
     * Scope: Filter by event category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('event_category', '=', $category);
    }

    /**
     * Scope: Filter by event type.
     */
    public function scopeEventType($query, string $type)
    {
        return $query->where('event_type', '=', $type);
    }

    /**
     * Scope: Filter by user.
     */
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', '=', $userId);
    }

    /**
     * Scope: Filter by severity.
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', '=', $severity);
    }

    /**
     * Scope: Get recent logs.
     */
    public function scopeRecent($query, int $limit = 100)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope: Get sensitive operations.
     */
    public function scopeSensitive($query)
    {
        return $query->where('is_sensitive', '=', true);
    }

    /**
     * Scope: Get logs within date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
