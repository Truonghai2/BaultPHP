<?php

namespace Modules\Admin\Application\CommandHandlers\Module;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Module\ModuleSettings;
use Core\Support\Facades\Audit;

/**
 * UpdateModuleSettingHandler
 * 
 * Handles the UpdateModuleSettingCommand.
 */
class UpdateModuleSettingHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $setting = ModuleSettings::where('module_name', '=', $command->moduleName)
            ->where('key', '=', $command->key)
            ->first();

        if ($setting) {
            $oldValue = $setting->value;
            $setting->type = $command->type;
            $setting->value = $command->value;
            
            if ($command->description) {
                $setting->description = $command->description;
            }
            if ($command->group) {
                $setting->group = $command->group;
            }
            
            $setting->save();

            Audit::log(
                'data_change',
                "Module setting updated: {$command->moduleName}.{$command->key}",
                [
                    'module' => $command->moduleName,
                    'setting_key' => $command->key,
                    'old_value' => $oldValue,
                    'new_value' => $command->value,
                    'type' => $command->type
                ],
                'info'
            );
        } else {
            $setting = new ModuleSettings([
                'module_name' => $command->moduleName,
                'key' => $command->key,
                'type' => $command->type,
                'description' => $command->description,
                'group' => $command->group
            ]);
            
            $setting->value = $command->value;
            $setting->save();

            Audit::log(
                'data_change',
                "Module setting created: {$command->moduleName}.{$command->key}",
                [
                    'module' => $command->moduleName,
                    'setting_key' => $command->key,
                    'value' => $command->value,
                    'type' => $command->type
                ],
                'info'
            );
        }

        return true;
    }
}

