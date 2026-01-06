<?php

declare(strict_types=1);

namespace Modules\Admin\Application\Services;

use Core\EventSourcing\AggregateRepository;
use Core\Support\Facades\Audit;
use Modules\Admin\Domain\Aggregates\ModuleAggregate;

/**
 * Module Aggregate Service
 *
 * Application service for module lifecycle management via Event Sourcing
 */
class ModuleAggregateService
{
    public function __construct(
        private AggregateRepository $aggregateRepository,
    ) {
    }

    /**
     * Install module (Event Sourced)
     */
    public function installModule(
        string $moduleId,
        string $name,
        string $version,
        array $dependencies = [],
        array $metadata = [],
    ): void {
        $module = new ModuleAggregate();
        $module->install($moduleId, $name, $version, $dependencies, $metadata);

        $this->aggregateRepository->save($module);

        Audit::log(
            'admin_action',
            "Module installed via Event Sourcing: {$name}",
            [
                'module_id' => $moduleId,
                'name' => $name,
                'version' => $version,
                'dependencies' => $dependencies,
                'method' => 'event_sourcing',
            ],
            'info',
        );
    }

    /**
     * Enable module
     */
    public function enableModule(string $moduleId): void
    {
        $module = $this->loadModule($moduleId);

        $module->enable();

        $this->aggregateRepository->save($module);

        Audit::log(
            'admin_action',
            "Module enabled: {$module->getName()}",
            [
                'module_id' => $moduleId,
                'name' => $module->getName(),
                'version' => $module->getVersion(),
            ],
            'info',
        );
    }

    /**
     * Disable module
     */
    public function disableModule(string $moduleId, string $reason = ''): void
    {
        $module = $this->loadModule($moduleId);

        $module->disable($reason);

        $this->aggregateRepository->save($module);

        Audit::log(
            'admin_action',
            "Module disabled: {$module->getName()}",
            [
                'module_id' => $moduleId,
                'name' => $module->getName(),
                'reason' => $reason,
            ],
            'warning',
        );
    }

    /**
     * Update module
     */
    public function updateModule(
        string $moduleId,
        string $newVersion,
        array $newDependencies = [],
        array $changeLog = [],
    ): void {
        $module = $this->loadModule($moduleId);

        $module->update($newVersion, $newDependencies, $changeLog);

        $this->aggregateRepository->save($module);

        Audit::log(
            'admin_action',
            "Module updated: {$module->getName()} {$module->getVersion()} → {$newVersion}",
            [
                'module_id' => $moduleId,
                'name' => $module->getName(),
                'old_version' => $module->getVersion(),
                'new_version' => $newVersion,
                'change_log' => $changeLog,
            ],
            'info',
        );
    }

    /**
     * Uninstall module
     */
    public function uninstallModule(string $moduleId, string $reason = ''): void
    {
        $module = $this->loadModule($moduleId);

        $module->uninstall($reason);

        $this->aggregateRepository->save($module);

        Audit::log(
            'admin_action',
            "Module uninstalled: {$module->getName()}",
            [
                'module_id' => $moduleId,
                'name' => $module->getName(),
                'reason' => $reason,
            ],
            'warning',
        );
    }

    /**
     * Resolve dependencies
     */
    public function resolveDependencies(string $moduleId, array $resolvedDependencies): void
    {
        $module = $this->loadModule($moduleId);

        $module->resolveDependencies($resolvedDependencies);

        $this->aggregateRepository->save($module);

        Audit::log(
            'admin_action',
            "Module dependencies resolved: {$module->getName()}",
            [
                'module_id' => $moduleId,
                'dependencies' => $resolvedDependencies,
            ],
            'info',
        );
    }

    /**
     * Get module state
     */
    public function getModuleState(string $moduleId): ?array
    {
        $module = $this->getModule($moduleId);

        if (!$module) {
            return null;
        }

        return [
            'id' => $module->getId(),
            'name' => $module->getName(),
            'version' => $module->getVersion(),
            'status' => $module->getStatus(),
            'is_installed' => $module->isInstalled(),
            'is_enabled' => $module->isEnabled(),
            'dependencies' => $module->getDependencies(),
            'metadata' => $module->getMetadata(),
            'installed_at' => $module->getInstalledAt()?->format('Y-m-d H:i:s'),
            'enabled_at' => $module->getEnabledAt()?->format('Y-m-d H:i:s'),
            'disabled_at' => $module->getDisabledAt()?->format('Y-m-d H:i:s'),
            'uninstalled_at' => $module->getUninstalledAt()?->format('Y-m-d H:i:s'),
            'version' => $module->getVersion(),
        ];
    }

    /**
     * Get module aggregate
     */
    public function getModule(string $moduleId): ?ModuleAggregate
    {
        return $this->aggregateRepository->load(ModuleAggregate::class, $moduleId);
    }

    /**
     * Load module or throw exception
     */
    private function loadModule(string $moduleId): ModuleAggregate
    {
        $module = $this->getModule($moduleId);

        if (!$module) {
            throw new \RuntimeException("Module {$moduleId} not found in event store");
        }

        return $module;
    }
}
