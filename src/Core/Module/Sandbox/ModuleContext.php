<?php

declare(strict_types=1);

namespace Core\Module\Sandbox;

/**
 * Request/worker-scoped "current module" for permission checks.
 *
 * When an extension point handler runs, the registry sets the context to the
 * module that registered it so sandboxed services (cache, etc.) can enforce
 * that module's declared permissions.
 */
final class ModuleContext
{
    private static ?string $currentModule = null;

    public static function getCurrentModule(): ?string
    {
        return self::$currentModule;
    }

    public static function setCurrentModule(?string $moduleName): void
    {
        self::$currentModule = $moduleName;
    }

    /**
     * Run a callable with the given module set as current, then restore previous context.
     */
    public static function runInModule(?string $moduleName, callable $fn): mixed
    {
        $previous = self::$currentModule;
        try {
            self::$currentModule = $moduleName;
            return $fn();
        } finally {
            self::$currentModule = $previous;
        }
    }
}
