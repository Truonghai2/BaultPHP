<?php

declare(strict_types=1);

namespace Modules\Admin\Domain\Aggregates;

use Core\EventSourcing\AggregateRoot;
use Modules\Admin\Domain\Aggregates\Events\ModuleDependenciesResolved;
use Modules\Admin\Domain\Aggregates\Events\ModuleDisabled;
use Modules\Admin\Domain\Aggregates\Events\ModuleEnabled;
use Modules\Admin\Domain\Aggregates\Events\ModuleInstalled;
use Modules\Admin\Domain\Aggregates\Events\ModuleUninstalled;
use Modules\Admin\Domain\Aggregates\Events\ModuleUpdated;

/**
 * Module Aggregate
 *
 * Event-sourced aggregate for module lifecycle management.
 *
 * This is a SELECTIVE implementation - only for critical Admin operations
 * that benefit from audit trail and versioning.
 *
 * Use cases:
 * - Track module installations/uninstalls
 * - Debug dependency issues
 * - Rollback problematic updates
 * - Compliance & audit
 */
class ModuleAggregate extends AggregateRoot
{
    private string $name;
    private string $version;
    private string $status = 'pending'; // pending, installed, enabled, disabled, uninstalled
    private array $dependencies = [];
    private array $metadata = [];
    private ?\DateTimeImmutable $installedAt = null;
    private ?\DateTimeImmutable $enabledAt = null;
    private ?\DateTimeImmutable $disabledAt = null;
    private ?\DateTimeImmutable $uninstalledAt = null;

    /**
     * Install module
     */
    public function install(
        string $id,
        string $name,
        string $version,
        array $dependencies = [],
        array $metadata = [],
    ): void {
        $this->recordThat(new ModuleInstalled(
            moduleId: $id,
            name: $name,
            version: $version,
            dependencies: $dependencies,
            metadata: $metadata,
        ));
    }

    /**
     * Resolve dependencies
     */
    public function resolveDependencies(array $resolvedDependencies): void
    {
        if ($this->status === 'uninstalled') {
            throw new \DomainException('Cannot resolve dependencies for uninstalled module');
        }

        $this->recordThat(new ModuleDependenciesResolved(
            moduleId: $this->id,
            dependencies: $resolvedDependencies,
        ));
    }

    /**
     * Enable module
     */
    public function enable(): void
    {
        if ($this->status === 'uninstalled') {
            throw new \DomainException('Cannot enable uninstalled module');
        }

        if ($this->status === 'enabled') {
            return; // Already enabled
        }

        if ($this->status !== 'installed' && $this->status !== 'disabled') {
            throw new \DomainException("Module must be installed before enabling (current: {$this->status})");
        }

        $this->recordThat(new ModuleEnabled(
            moduleId: $this->id,
        ));
    }

    /**
     * Disable module
     */
    public function disable(string $reason = ''): void
    {
        if ($this->status !== 'enabled') {
            return; // Not enabled
        }

        $this->recordThat(new ModuleDisabled(
            moduleId: $this->id,
            reason: $reason,
        ));
    }

    /**
     * Update module
     */
    public function update(
        string $newVersion,
        array $newDependencies = [],
        array $changeLog = [],
    ): void {
        if ($this->status === 'uninstalled') {
            throw new \DomainException('Cannot update uninstalled module');
        }

        if ($newVersion === $this->version) {
            return; // No version change
        }

        $this->recordThat(new ModuleUpdated(
            moduleId: $this->id,
            oldVersion: $this->version,
            newVersion: $newVersion,
            dependencies: $newDependencies,
            changeLog: $changeLog,
        ));
    }

    /**
     * Uninstall module
     */
    public function uninstall(string $reason = ''): void
    {
        if ($this->status === 'uninstalled') {
            return; // Already uninstalled
        }

        if ($this->status === 'enabled') {
            throw new \DomainException('Module must be disabled before uninstalling');
        }

        $this->recordThat(new ModuleUninstalled(
            moduleId: $this->id,
            reason: $reason,
        ));
    }

    // ==================== Event Application Methods ====================

    protected function applyModuleInstalled(ModuleInstalled $event): void
    {
        $this->id = $event->moduleId;
        $this->name = $event->name;
        $this->version = $event->version;
        $this->dependencies = $event->dependencies;
        $this->metadata = $event->metadata;
        $this->status = 'installed';
        $this->installedAt = $event->occurredAt;
    }

    protected function applyModuleDependenciesResolved(ModuleDependenciesResolved $event): void
    {
        $this->dependencies = $event->dependencies;
    }

    protected function applyModuleEnabled(ModuleEnabled $event): void
    {
        $this->status = 'enabled';
        $this->enabledAt = $event->occurredAt;
        $this->disabledAt = null;
    }

    protected function applyModuleDisabled(ModuleDisabled $event): void
    {
        $this->status = 'disabled';
        $this->disabledAt = $event->occurredAt;
    }

    protected function applyModuleUpdated(ModuleUpdated $event): void
    {
        $this->version = $event->newVersion;
        $this->dependencies = $event->dependencies;
    }

    protected function applyModuleUninstalled(ModuleUninstalled $event): void
    {
        $this->status = 'uninstalled';
        $this->uninstalledAt = $event->occurredAt;
    }

    // ==================== Getters ====================

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isInstalled(): bool
    {
        return in_array($this->status, ['installed', 'enabled', 'disabled']);
    }

    public function isEnabled(): bool
    {
        return $this->status === 'enabled';
    }

    public function getInstalledAt(): ?\DateTimeImmutable
    {
        return $this->installedAt;
    }

    public function getEnabledAt(): ?\DateTimeImmutable
    {
        return $this->enabledAt;
    }

    public function getDisabledAt(): ?\DateTimeImmutable
    {
        return $this->disabledAt;
    }

    public function getUninstalledAt(): ?\DateTimeImmutable
    {
        return $this->uninstalledAt;
    }
}
