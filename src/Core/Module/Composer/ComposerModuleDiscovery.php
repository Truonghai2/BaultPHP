<?php

declare(strict_types=1);

namespace Core\Module\Composer;

use Core\Application;
use Core\Module\ModuleManifest;

/**
 * Discovers BaultFrame modules installed as Composer packages.
 *
 * A package is treated as a module if:
 * - its type is "bault-module", or
 * - it has extra.bault.module (with at least "name" and "providers").
 *
 * Enabled state for composer modules is taken from config('modules.composer_disabled').
 * If the package name is in that list, it is disabled; otherwise enabled.
 */
final class ComposerModuleDiscovery
{
    private const EXTRA_KEY = 'bault.module';
    private const PACKAGE_TYPE = 'bault-module';

    /** @var array<string, string> module name => package base path */
    private array $pathByModuleName = [];

    /** @var list<ModuleManifest> */
    private array $manifests = [];

    private bool $scanned = false;

    public function __construct(
        private readonly Application $app,
    ) {
    }

    /**
     * Get the filesystem path for a module (from Composer package or Modules/).
     * Returns null only for composer modules that were discovered; for others use basePath('Modules/'.$name).
     */
    public function getPackagePath(string $moduleName): ?string
    {
        $this->scanIfNeeded();
        return $this->pathByModuleName[$moduleName] ?? null;
    }

    /**
     * Get all enabled manifests from Composer packages.
     *
     * @return list<ModuleManifest>
     */
    public function getManifests(): array
    {
        $this->scanIfNeeded();
        return $this->manifests;
    }

    private function scanIfNeeded(): void
    {
        if ($this->scanned) {
            return;
        }
        $this->scanned = true;
        $vendorDir = $this->app->basePath('vendor');
        if (!is_dir($vendorDir)) {
            return;
        }
        $disabled = $this->app->make('config')->get('modules.composer_disabled', []);
        $disabled = is_array($disabled) ? $disabled : [];

        foreach ($this->findVendorPackages($vendorDir) as $packagePath) {
            $composerPath = $packagePath . '/composer.json';
            if (!is_file($composerPath)) {
                continue;
            }
            try {
                $json = json_decode((string) file_get_contents($composerPath), true);
                if (!is_array($json)) {
                    continue;
                }
                $extra = $json['extra'] ?? [];
                $bault = $extra[self::EXTRA_KEY] ?? null;
                if (!is_array($bault)) {
                    if (($json['type'] ?? '') !== self::PACKAGE_TYPE) {
                        continue;
                    }
                    $bault = ['name' => $this->inferNameFromPackage($json['name'] ?? ''), 'providers' => []];
                }
                if (empty($bault['name']) || empty($bault['providers']) || !is_array($bault['providers'])) {
                    continue;
                }
                $packageName = $json['name'] ?? 'unknown';
                if (in_array($packageName, $disabled, true)) {
                    continue;
                }
                $manifest = ModuleManifest::fromComposerPackage($composerPath, true);
                $this->manifests[] = $manifest;
                $this->pathByModuleName[$manifest->name] = $packagePath;
            } catch (\Throwable) {
                continue;
            }
        }
    }

    /**
     * @return list<string> paths to package roots (vendor/vendor/package)
     */
    private function findVendorPackages(string $vendorDir): array
    {
        $paths = [];
        $dirs = scandir($vendorDir);
        if ($dirs === false) {
            return $paths;
        }
        foreach ($dirs as $vendor) {
            if ($vendor === '.' || $vendor === '..' || !is_dir($vendorDir . '/' . $vendor)) {
                continue;
            }
            $vendorPath = $vendorDir . '/' . $vendor;
            $sub = scandir($vendorPath);
            if ($sub === false) {
                continue;
            }
            foreach ($sub as $package) {
                if ($package === '.' || $package === '..') {
                    continue;
                }
                $path = $vendorPath . '/' . $package;
                if (is_dir($path)) {
                    $paths[] = $path;
                }
            }
        }
        return $paths;
    }

    private function inferNameFromPackage(string $packageName): string
    {
        $parts = explode('/', $packageName);
        $last = end($parts);
        if ($last === false || $last === '') {
            return 'Module';
        }
        $last = preg_replace('/^bault-module-/', '', $last);
        $last = str_replace('-', '', ucwords($last, '-'));
        return $last;
    }
}
