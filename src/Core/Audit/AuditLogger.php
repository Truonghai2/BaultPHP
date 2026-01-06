<?php

namespace Core\Audit;

use Core\Audit\Models\AuditLog;
use Core\Contracts\Auth\Authenticatable;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Central Audit Logger
 *
 * Provides convenient methods to log various types of events.
 */
class AuditLogger
{
    protected ?ServerRequestInterface $request = null;
    protected ?Authenticatable $user = null;

    public function __construct()
    {
        // Auto-detect request and user from container
        try {
            $this->request = app(ServerRequestInterface::class);
        } catch (\Exception $e) {
            // Request not available (CLI context)
        }

        try {
            $this->user = auth()->user();
        } catch (\Exception $e) {
            // User not authenticated
        }
    }

    /**
     * Log an OAuth event.
     */
    public function oauth(
        string $eventType,
        array $data = [],
        string $severity = 'info',
    ): AuditLog {
        return $this->log(
            eventType: $eventType,
            eventCategory: 'oauth',
            metadata: $data,
            severity: $severity,
            isSensitive: true,
        );
    }

    /**
     * Log an authentication event.
     */
    public function auth(
        string $eventType,
        ?Authenticatable $user = null,
        array $data = [],
        string $severity = 'info',
    ): AuditLog {
        return $this->log(
            eventType: $eventType,
            eventCategory: 'auth',
            user: $user,
            metadata: $data,
            severity: $severity,
            isSensitive: true,
        );
    }

    /**
     * Log a model change (create, update, delete).
     */
    public function model(
        string $action, // created, updated, deleted
        $model,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $modelClass = get_class($model);
        $modelId = method_exists($model, 'getKey') ? $model->getKey() : null;

        return $this->log(
            eventType: "model.{$action}",
            eventCategory: 'crud',
            auditableType: $modelClass,
            auditableId: $modelId,
            oldValues: $oldValues,
            newValues: $newValues,
            description: "{$modelClass} {$action}",
            severity: 'info',
        );
    }

    /**
     * Log a system event.
     */
    public function system(
        string $eventType,
        string $description,
        array $metadata = [],
        string $severity = 'info',
    ): AuditLog {
        return $this->log(
            eventType: $eventType,
            eventCategory: 'system',
            description: $description,
            metadata: $metadata,
            severity: $severity,
        );
    }

    /**
     * Log a security event.
     */
    public function security(
        string $eventType,
        string $description,
        array $metadata = [],
        string $severity = 'warning',
    ): AuditLog {
        return $this->log(
            eventType: $eventType,
            eventCategory: 'security',
            description: $description,
            metadata: $metadata,
            severity: $severity,
            isSensitive: true,
        );
    }

    /**
     * Generic log method.
     */
    public function log(
        string $eventType,
        string $eventCategory,
        ?Authenticatable $user = null,
        ?string $auditableType = null,
        ?string $auditableId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        string $severity = 'info',
        bool $isSensitive = false,
    ): AuditLog {
        $user = $user ?? $this->user;

        $data = [
            'event_type' => $eventType,
            'event_category' => $eventCategory,
            'user_id' => $user?->getAuthIdentifier(),
            'user_type' => $user ? get_class($user) : null,
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'description' => $description,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'severity' => $severity,
            'is_sensitive' => $isSensitive,
        ];

        return AuditLog::create($data);
    }

    /**
     * Get IP address from request.
     */
    protected function getIpAddress(): ?string
    {
        if (!$this->request) {
            return null;
        }

        $serverParams = $this->request->getServerParams();

        // Check for proxy headers
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                // Handle comma-separated IPs (from proxies)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? null;
    }

    /**
     * Get user agent from request.
     */
    protected function getUserAgent(): ?string
    {
        if (!$this->request) {
            return null;
        }

        return $this->request->getHeaderLine('User-Agent') ?: null;
    }

    /**
     * Set custom user for this logger instance.
     */
    public function forUser(?Authenticatable $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Set custom request for this logger instance.
     */
    public function forRequest(?ServerRequestInterface $request): self
    {
        $this->request = $request;
        return $this;
    }
}
