<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class ModuleClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'module:clear';
    }

    public function description(): string
    {
        return 'Remove the module cache file.';
    }

    public function handle(): int
    {
        $this->comment('Clearing Module Cache...');

        $cachePath = $this->app->basePath('bootstrap/cache/modules.php');

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->info('Module cache cleared successfully!');
        } else {
            $this->comment('Module cache not found. Nothing to clear.');
        }
        return 0;
    }
}
