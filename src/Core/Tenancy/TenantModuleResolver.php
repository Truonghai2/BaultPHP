<?php

declare(strict_types=1);

namespace Core\Tenancy;

use Core\Application;
use Core\Module\Composer\ComposerModuleDiscovery;
use Core\Module\ModuleManifest;
use Core\Support\Context;

/**
 * Returns enabled module names for the current request context (tenant-aware).
 * When tenancy is disabled or no tenant in context, returns globally enabled modules.
 */
class TenantModuleResolver
{
    private ?array $globalEnabledNames = null;
    /** @var array<int, list<string>> */
    private array $tenantEnabledById = [];

    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get list of module names that are enabled for the current context.
     *
     * @return list<string>
     */
    public function getEnabledModuleNames(): array
    {
        $global = $this->getGlobalEnabledModuleNames();
        $tenantId = Context::get('tenant_id');
        if ($tenantId === null || !config('tenancy.enabled', false)) {
            return $global;
        }
        $tenantAllowed = $this->getTenantEnabledModuleNames((int) $tenantId);
        return array_values(array_intersect($global, $tenantAllowed));
    }

    /**
     * Check if a module is enabled for the current context.
     */
    public function isModuleEnabledForCurrentContext(string $moduleName): bool
    {
        return in_array($moduleName, $this->getEnabledModuleNames(), true);
    }

    /**
     * Globally enabled module names (from module.json enabled=true and Composer).
     *
     * @return list<string>
     */
    protected function getGlobalEnabledModuleNames(): array
    {
        if ($this->globalEnabledNames !== null) {
            return $this->globalEnabledNames;
        }
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
            foreach ($composer->getManifests() as $manifest) {
                $names[] = $manifest->name;
            }
        }
        $this->globalEnabledNames = array_values(array_unique($names));
        return $this->globalEnabledNames;
    }

    /**
     * Module names enabled for this tenant (from tenant_modules where enabled=1).
     *
     * @return list<string>
     */
    protected function getTenantEnabledModuleNames(int $tenantId): array
    {
        if (isset($this->tenantEnabledById[$tenantId])) {
            return $this->tenantEnabledById[$tenantId];
        }
        try {
            $rows = TenantModule::where('tenant_id', $tenantId)->where('enabled', true)->get();
            $this->tenantEnabledById[$tenantId] = $rows->pluck('module_name')->values()->all();
        } catch (\Throwable) {
            $this->tenantEnabledById[$tenantId] = [];
        }
        return $this->tenantEnabledById[$tenantId];
    }

    /**
     * Clear in-memory cache (e.g. after tenant switch in tests).
     */
    public function clearCache(): void
    {
        $this->globalEnabledNames = null;
        $this->tenantEnabledById = [];
    }
}
