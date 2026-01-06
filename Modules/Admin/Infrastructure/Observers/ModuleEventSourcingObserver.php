<?php

declare(strict_types=1);

namespace Modules\Admin\Infrastructure\Observers;

use Modules\Admin\Application\Services\ModuleAggregateService;
use Modules\Admin\Infrastructure\Models\Module;
use Illuminate\Support\Facades\Log;

/**
 * Module Event Sourcing Observer
 * 
 * Automatically tracks module lifecycle events
 */
class ModuleEventSourcingObserver
{
    public function __construct(
        private ModuleAggregateService $moduleService
    ) {
    }

    public function created(Module $module): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            $this->moduleService->installModule(
                moduleId: $module->name, // Use module name as ID
                name: $module->display_name ?? $module->name,
                version: $module->version ?? '1.0.0',
                dependencies: $module->dependencies ?? [],
                metadata: [
                    'description' => $module->description ?? '',
                    'auto_installed' => true
                ]
            );

            Log::channel('event_sourcing')->info('Module installed via Event Sourcing', [
                'module_id' => $module->name
            ]);
        } catch (\Exception $e) {
            Log::error('Event Sourcing error on module install', [
                'error' => $e->getMessage(),
                'module' => $module->name
            ]);
        }
    }

    public function updated(Module $module): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            // Check what changed
            if ($module->isDirty('enabled')) {
                if ($module->enabled) {
                    $this->moduleService->enableModule($module->name);
                } else {
                    $this->moduleService->disableModule(
                        $module->name,
                        'Disabled via admin panel'
                    );
                }
            }

            if ($module->isDirty('version')) {
                $this->moduleService->updateModule(
                    moduleId: $module->name,
                    newVersion: $module->version,
                    newDependencies: $module->dependencies ?? [],
                    changeLog: ['Updated via admin panel']
                );
            }
        } catch (\Exception $e) {
            Log::error('Event Sourcing error on module update', [
                'error' => $e->getMessage(),
                'module' => $module->name
            ]);
        }
    }

    public function deleted(Module $module): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            $this->moduleService->uninstallModule(
                moduleId: $module->name,
                reason: 'Uninstalled via admin panel'
            );

            Log::channel('event_sourcing')->info('Module uninstalled via Event Sourcing', [
                'module_id' => $module->name
            ]);
        } catch (\Exception $e) {
            Log::error('Event Sourcing error on module uninstall', [
                'error' => $e->getMessage(),
                'module' => $module->name
            ]);
        }
    }

    private function shouldRecord(): bool
    {
        return config('event-sourcing.dual_write', true)
            && config('event-sourcing.auto_record.enabled', true)
            && config('event-sourcing.modules.admin.modules.track_installations', true);
    }
}

