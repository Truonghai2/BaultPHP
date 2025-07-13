<?php

namespace Core\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Module\Module; // Assuming this is the correct ORM Model
use Throwable;

class ModuleCacheCommand extends BaseCommand
{
    /**
     * Create a new command instance.
     */
    public function __construct(private Application $app)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'module:cache';
    }

    public function description(): string
    {
        return 'Create a cache file for faster module registration.';
    }

    public function fire(): void
    {
        $this->io->title('Caching Enabled Modules');

        $cachePath = $this->app->basePath('bootstrap/cache/modules.php');

        try {
            $enabledModuleNames = Module::where('enabled', true)->pluck('name')->all();
        } catch (Throwable $e) {
            $this->io->error('Could not retrieve modules from the database. Error: ' . $e->getMessage() . ' Please ensure your database is configured and migrated correctly.');
            return;
        }

        $exported = var_export($enabledModuleNames, true);
        $content = "<?php\n\nreturn " . $exported . ";\n";

        file_put_contents($cachePath, $content);
        $this->io->success('Enabled modules cached successfully!');
    }
}