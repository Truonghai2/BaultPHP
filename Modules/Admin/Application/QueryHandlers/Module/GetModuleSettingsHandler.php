<?php

namespace Modules\Admin\Application\QueryHandlers\Module;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Core\Module\ModuleSettings;
use Modules\Admin\Application\Queries\Module\GetModuleSettingsQuery;

/**
 * GetModuleSettingsHandler
 * 
 * Handles GetModuleSettingsQuery.
 */
class GetModuleSettingsHandler implements QueryHandlerInterface
{
    public function handle(GetModuleSettingsQuery $query): array
    {
        $queryBuilder = ModuleSettings::where('module_name', '=', $query->moduleName);

        if ($query->group) {
            $queryBuilder->where('group', '=', $query->group);
        }

        if ($query->publicOnly) {
            $queryBuilder->where('is_public', '=', true);
        }

        $settings = $queryBuilder->orderBy('order', 'asc')
            ->orderBy('key', 'asc')
            ->get();

        // Return settings with casted values
        return $settings->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->getCastedValue(),
                'type' => $setting->type,
                'description' => $setting->description,
                'group' => $setting->group,
                'is_public' => $setting->is_public,
            ];
        })->toArray();
    }
}

