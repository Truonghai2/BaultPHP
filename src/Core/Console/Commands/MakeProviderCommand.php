<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class MakeProviderCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:provider {name : The name of the provider class} {--module= : Create the provider in a specific module}';
    }

    public function description(): string
    {
        return 'Create a new service provider class';
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->option('module');

        if (!preg_match('/^[A-Z][a-zA-Z0-9_]*ServiceProvider$/', $name)) {
            $this->error('Invalid provider name. Please use StudlyCase format and end with "ServiceProvider" (e.g., MyAwesomeServiceProvider).');
            return self::FAILURE;
        }

        $className = $name;
        $locationDescription = 'src/Providers/';
        $namespace = 'App\\Providers';
        $path = app_path("Providers/{$className}.php");

        if ($module) {
            $module = ucfirst($module);
            $modulePath = base_path('Modules/' . $module);
            if (!is_dir($modulePath)) {
                $this->error("Module [{$module}] does not exist.");
                return self::FAILURE;
            }
            $namespace = "Modules\\{$module}\\Providers";
            $path = "{$modulePath}/Providers/{$className}.php";
            $locationDescription = "Modules/{$module}/Providers/";
        }

        if (file_exists($path)) {
            $this->error("Provider class [{$className}] already exists!");
            return self::FAILURE;
        }

        $this->ensureDirectoryExists($path);

        $stub = $this->getStubContent($className, $namespace);

        file_put_contents($path, $stub);

        $this->info("Provider [{$className}] created successfully.");
        $this->comment(" -> Located at: {$locationDescription}{$className}.php");
        $this->comment(' -> Remember to register it!');

        return self::SUCCESS;
    }

    /**
     * Ensure the directory for the file exists.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    /**
     * Get the provider stub content.
     */
    protected function getStubContent(string $className, string $namespace): string
    {
        return <<<STUB
<?php

namespace {$namespace};

use Core\Support\ServiceProvider;

class {$className} extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
STUB;
    }
}
