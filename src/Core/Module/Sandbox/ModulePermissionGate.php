<?php

declare(strict_types=1);

namespace Core\Module\Sandbox;

/**
 * Decides whether the current module context is allowed to perform an action
 * (e.g. cache:read, cache:write) based on its declared permissions.
 *
 * When no module is in context (e.g. core or CLI), all actions are allowed.
 */
final class ModulePermissionGate
{
    public function __construct(
        private readonly ModulePermissionRegistry $registry,
    ) {
    }

    /**
     * Check if the current module (if any) is allowed the given permission.
     *
     * Permission format: "resource:action", e.g. "cache:read", "cache:write",
     * "database:read", "database:write", "storage:read", "storage:write",
     * "events:subscribe", "events:publish", "network:out".
     */
    public function allows(string $permission): bool
    {
        $module = ModuleContext::getCurrentModule();
        if ($module === null || $module === '') {
            return true;
        }
        return $this->registry->hasPermission($module, $permission);
    }

    /**
     * Throw if the current module is not allowed the given permission.
     *
     * @throws ModulePermissionDeniedException
     */
    public function authorize(string $permission): void
    {
        if (!$this->allows($permission)) {
            throw new ModulePermissionDeniedException(
                ModuleContext::getCurrentModule() ?? 'unknown',
                $permission,
            );
        }
    }
}
