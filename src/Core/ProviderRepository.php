<?php

namespace Core;

use Core\Module\Composer\ComposerModuleDiscovery;
use Core\Module\ModuleManifest;
use Core\Tenancy\TenantModuleResolver;

/**
 * Class ProviderRepository
 *
 * This class is responsible for discovering and collecting all service providers
 * for the application, both from the core configuration and from enabled modules.
 * It decouples the AppKernel from the logic of how providers are found.
 *
 * Lazy loading (2.4): only "on_boot" modules are included in getBootProviders() / getAllProviders().
 * Modules with activate=on_request are discovered via getLazyModuleInfos() and loaded on demand.
 */
class ProviderRepository
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a flattened list of all service providers to be registered at bootstrap.
     * Includes core providers and only enabled modules with activate=on_boot (default).
     *
     * @return string[]
     */
    public function getAllProviders(): array
    {
        return $this->getBootProviders();
    }

    /**
     * Same as getAllProviders(); use for explicit "boot-only" semantics.
     *
     * @return string[]
     */
    public function getBootProviders(): array
    {
        $coreProviders = config('app.providers', []);
        $moduleProviders = $this->discoverBootModuleProviders();

        return array_values(array_unique(array_merge($coreProviders, $moduleProviders)));
    }

    /**
     * Get infos for enabled modules that use activate=on_request (lazy by path).
     * Used to build the route→module map and to load providers when a request matches.
     *
     * @return list<array{name: string, providers: list<string>, pathPrefixes: list<string>}>
     */
    public function getLazyModuleInfos(): array
    {
        $infos = [];
        foreach ($this->getEnabledManifests() as $manifest) {
            if (!$manifest->isActivateOnRequest()) {
                continue;
            }
            $prefixes = $manifest->getPathPrefixes();
            if ($prefixes === []) {
                continue;
            }
            $providers = [];
            foreach ($manifest->providers as $p) {
                if (is_string($p) && class_exists($p)) {
                    $providers[] = $p;
                }
            }
            $infos[] = [
                'name'          => $manifest->name,
                'providers'      => $providers,
                'pathPrefixes'   => $prefixes,
            ];
        }
        return $infos;
    }

    /**
     * Discovers service providers from enabled modules that are loaded at boot (activate=on_boot).
     *
     * @return string[]
     */
    protected function discoverBootModuleProviders(): array
    {
        $discovered = [];
        foreach ($this->getEnabledManifests() as $manifest) {
            if (!$manifest->isActivateOnBoot()) {
                continue;
            }
            foreach ($manifest->providers as $provider) {
                if (is_string($provider) && class_exists($provider)) {
                    $discovered[] = $provider;
                }
            }
        }
        return $discovered;
    }

    /**
     * Load and parse all enabled module manifests (tenant-aware when tenancy enabled).
     *
     * @return list<ModuleManifest>
     */
    protected function getEnabledManifests(): array
    {
        $enabledNames = $this->app->bound(TenantModuleResolver::class)
            ? $this->app->make(TenantModuleResolver::class)->getEnabledModuleNames()
            : $this->getEnabledModuleNamesFallback();

        $manifests = [];
        foreach ($enabledNames as $name) {
            $manifest = $this->loadManifestByName($name);
            if ($manifest !== null) {
                $manifests[] = $manifest;
            }
        }
        return $manifests;
    }

    /**
     * Fallback when TenantModuleResolver not bound (e.g. early bootstrap).
     *
     * @return list<string>
     */
    protected function getEnabledModuleNamesFallback(): array
    {
        $names = [];
        $paths = glob($this->app->basePath('Modules/*/module.json')) ?: [];
        foreach ($paths as $path) {
            $data = @json_decode((string) file_get_contents($path), true);
            if (is_array($data) && !empty($data['name']) && !empty($data['enabled']) && $data['enabled'] === true) {
                $names[] = $data['name'];
            }
        }
        if ($this->app->bound(ComposerModuleDiscovery::class)) {
            $composer = $this->app->make(ComposerModuleDiscovery::class);
            foreach ($composer->getManifests() as $m) {
                $names[] = $m->name;
            }
        }
        return array_values(array_unique($names));
    }

    protected function loadManifestByName(string $name): ?ModuleManifest
    {
        $path = $this->app->basePath('Modules/' . $name . '/module.json');
        if (file_exists($path)) {
            $data = @json_decode((string) file_get_contents($path), true);
            if (is_array($data) && !empty($data['name'])) {
                return ModuleManifest::fromArray($data);
            }
        }
        if ($this->app->bound(ComposerModuleDiscovery::class)) {
            $composer = $this->app->make(ComposerModuleDiscovery::class);
            foreach ($composer->getManifests() as $manifest) {
                if ($manifest->name === $name) {
                    return $manifest;
                }
            }
        }
        return null;
    }
}
