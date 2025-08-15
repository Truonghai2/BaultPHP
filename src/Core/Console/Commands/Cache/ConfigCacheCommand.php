<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;

class ConfigCacheCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'config:cache';
    }

    public function description(): string
    {
        return 'Create a cache file for faster configuration loading.';
    }

    public function handle(): int
    {
        $this->info('Caching configuration...');

        $config = [];
        $configPath = $this->app->basePath('config');
        $files = glob($configPath . '/*.php');

        foreach ($files as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $config[$key] = require $file;
        }

        $cachePath = $this->app->bootstrapPath('cache/config.php');
        $cacheDir = dirname($cachePath);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $exportedConfig = '<?php return ' . var_export($config, true) . ';';
        file_put_contents($cachePath, $exportedConfig);

        $this->info('Configuration cached successfully!');

        return self::SUCCESS;
    }
}
