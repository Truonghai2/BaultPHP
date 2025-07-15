<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\Module\Module; 
use Throwable;

class ModuleCacheCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'module:cache';
    }

    public function description(): string
    {
        return 'Create a cache file for faster module registration.';
    }

    public function handle(): int
    {
        $this->comment('Caching Enabled Modules...');

        $cachePath = $this->app->basePath('bootstrap/cache/modules.php');

        try {
            $enabledModuleNames = Module::where('enabled', true)->pluck('name')->all();
        } catch (Throwable $e) {
            $this->error('Could not retrieve modules from the database. Please ensure it is configured and migrated.');
            $this->line('Error: ' . $e->getMessage());
            return 1;
        }

        $exported = var_export($enabledModuleNames, true);
        $content = "<?php\n\nreturn " . $exported . ";\n";

        file_put_contents($cachePath, $content);
        $this->info('Enabled modules cached successfully!');
        return 0;
    }
}