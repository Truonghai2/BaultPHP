<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class CacheClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Flush the application cache (config, route, and module).';
    }

    public function handle(): int
    {
        $this->comment('Clearing All Application Caches');

        $this->clearConfigCache();
        $this->clearRouteCache();
        $this->clearModuleCache();

        $this->info('All application caches have been cleared!');
        return 0;
    }

    private function clearConfigCache(): void
    {
        // The $this->app property is inherited from BaseCommand
        $cachePath = $this->app->getCachedProvidersPath();
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->line('<info>✔ Config cache cleared.</info>');
        } else {
            $this->line('<comment>› Config cache not found.</comment>');
        }
    }

    private function clearRouteCache(): void
    {
        $cachePath = $this->app->getCachedRoutesPath();
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->line('<info>✔ Route cache cleared.</info>');
        } else {
            $this->line('<comment>› Route cache not found.</comment>');
        }
    }

    private function clearModuleCache(): void
    {
        $cachePath = $this->app->basePath('bootstrap/cache/modules.php');
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->line('<info>✔ Module cache cleared.</info>');
        } else {
            $this->line('<comment>› Module cache not found.</comment>');
        }
    }
}
