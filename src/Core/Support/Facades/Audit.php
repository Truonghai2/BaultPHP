<?php

namespace Core\Support\Facades;

use Core\Audit\AuditLogger;

/**
 * Audit Facade
 * 
 * @method static \Core\Audit\Models\AuditLog oauth(string $eventType, array $data = [], string $severity = 'info')
 * @method static \Core\Audit\Models\AuditLog auth(string $eventType, ?\Core\Contracts\Auth\Authenticatable $user = null, array $data = [], string $severity = 'info')
 * @method static \Core\Audit\Models\AuditLog model(string $action, $model, ?array $oldValues = null, ?array $newValues = null)
 * @method static \Core\Audit\Models\AuditLog system(string $eventType, string $description, array $metadata = [], string $severity = 'info')
 * @method static \Core\Audit\Models\AuditLog security(string $eventType, string $description, array $metadata = [], string $severity = 'warning')
 * @method static \Core\Audit\Models\AuditLog log(string $eventType, string $eventCategory, ?\Core\Contracts\Auth\Authenticatable $user = null, ?string $auditableType = null, ?string $auditableId = null, ?string $description = null, ?array $oldValues = null, ?array $newValues = null, ?array $metadata = null, string $severity = 'info', bool $isSensitive = false)
 * @method static AuditLogger forUser(?\Core\Contracts\Auth\Authenticatable $user)
 * @method static AuditLogger forRequest(?\Psr\Http\Message\ServerRequestInterface $request)
 */
class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuditLogger::class;
    }
}

