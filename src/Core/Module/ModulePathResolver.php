<?php

declare(strict_types=1);

namespace Core\Module;

use Core\Application;
use Core\Module\Composer\ComposerModuleDiscovery;

/**
 * Resolves the filesystem path for a module (either Modules/Name or vendor package).
 */
final class ModulePathResolver
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    /**
     * Get the base path for a module (where module.json, events.php, extensions.php, manifest.yaml live).
     */
    public function pathFor(string $moduleName): string
    {
        if ($this->app->bound(ComposerModuleDiscovery::class)) {
            $path = $this->app->make(ComposerModuleDiscovery::class)->getPackagePath($moduleName);
            if ($path !== null) {
                return $path;
            }
        }
        return $this->app->basePath('Modules/' . $moduleName);
    }
}
