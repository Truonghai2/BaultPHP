<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
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

        $enabledModuleNames = [];
        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));

        foreach ($moduleJsonPaths as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!empty($data['name']) && !empty($data['enabled']) && $data['enabled'] === true) {
                $enabledModuleNames[] = $data['name'];
            }
        }

        // Sắp xếp để đảm bảo file cache nhất quán

        $exported = var_export($enabledModuleNames, true);
        $content = "<?php\n\nreturn " . $exported . ";\n";

        file_put_contents($cachePath, $content);
        $this->info('Enabled modules cached successfully!');
        return 0;
    }
}
