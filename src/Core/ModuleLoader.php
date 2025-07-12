<?php 
namespace Core;

class ModuleLoader
{
    public static function load(string $modulesPath): void
    {
        foreach (glob($modulesPath . '/*/Providers/ModuleServiceProvider.php') as $providerPath) {
            $module = basename(dirname(dirname($providerPath)));
            $provider = "Modules\\{$module}\\Providers\\ModuleServiceProvider";

            if (class_exists($provider)) {
                app()->register($provider);
            }

            $configPath = $modulesPath . "/{$module}/config.php";
            if (file_exists($configPath)) {
                $key = strtolower($module);
                config_module([$key => require $configPath]);
            }
        }
    }
}
