<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class MakeModuleCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'ddd:make-module {name : The name of the module (e.g., Post, User)}';
    }

    public function description(): string
    {
        return 'Create a new module with a standard DDD structure.';
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $name = ucfirst($name);
        $modulePath = base_path("Modules/{$name}");

        if (is_dir($modulePath)) {
            $this->io->error("Module '{$name}' already exists.");
            return self::FAILURE;
        }

        $this->io->info("Creating module [{$name}]...");

        $this->createDirectories($modulePath);
        $this->createScaffoldFiles($modulePath, $name);

        $this->io->success("Module [{$name}] created successfully.");
        $this->io->comment("-> Module is enabled by default in <fg=yellow>Modules/{$name}/module.json</>. No further action is needed.");
        return self::SUCCESS;
    }

    /**
     * Create the standard directory structure for the module.
     */
    private function createDirectories(string $modulePath): void
    {
        $directories = [
            'Application/Commands',
            'Application/Handlers',
            'Application/Listeners',
            'Application/Policies',
            'Application/Queries',
            'Domain/Entities',
            'Domain/Events',
            'Domain/Repositories',
            'Domain/Services',
            'Http/Controllers',
            'Http/Requests',
            'Infrastructure/Migrations',
            'Infrastructure/Models',
            'Infrastructure/Repositories',
            'Providers',
        ];

        foreach ($directories as $dir) {
            $fullPath = $modulePath . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                // Add a .gitkeep file to ensure empty directories are committed to git
                file_put_contents($fullPath . '/.gitkeep', '');
            }
        }
    }

    /**
     * Create the basic scaffold files for the module.
     */
    private function createScaffoldFiles(string $modulePath, string $name): void
    {
        $this->createServiceProvider($modulePath, $name);
        $this->createEventsFile($modulePath, $name);
        $this->createModuleJson($modulePath, $name);
        $this->createConfigFile($modulePath, $name);
        $this->createPermissionsFile($modulePath, $name);
    }

    /**
     * Create the module's primary service provider.
     */
    private function createServiceProvider(string $modulePath, string $name): void
    {
        $providerPath = "{$modulePath}/Providers/{$name}ServiceProvider.php";
        $stub = <<<PHP
<?php

namespace Modules\\{$name}\\Providers;

use Core\Support\ServiceProvider;

class {$name}ServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register bindings for this module, e.g., repositories.
        //
        // \$this->app->bind(
        //     \Modules\\{$name}\\Domain\\Repositories\\PostRepositoryInterface::class,
        //     \Modules\\{$name}\\Infrastructure\\Repositories\\EloquentPostRepository::class
        // );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Booting services, maybe loading routes or event listeners.
        // Attribute-based routing is auto-discovered, so no need to load routes here.
        // Event listeners are also auto-discovered from the module's events.php file.
    }
}
PHP;
        file_put_contents($providerPath, $stub);
    }

    /**
     * Create the module's event registration file.
     */
    private function createEventsFile(string $modulePath, string $name): void
    {
        $eventsPath = "{$modulePath}/events.php";
        $stub = <<<PHP
<?php

// This file is automatically discovered by the EventServiceProvider.
// You can register event-listener mappings here for the {$name} module.

return [
    // \Modules\\{$name}\\Domain\\Events\\SomeEvent::class => [
    //     \Modules\\{$name}\\Application\\Listeners\\HandleSomeEvent::class,
    // ],
];
PHP;
        file_put_contents($eventsPath, $stub);
    }

    /**
     * Create the module's configuration file.
     */
    private function createConfigFile(string $modulePath, string $name): void
    {
        $configPath = "{$modulePath}/config.php";
        $stub = <<<PHP
<?php

return [
    // Configuration for the {$name} module.
];
PHP;
        file_put_contents($configPath, $stub);
    }

    /**
     * Create the module's permissions definition file.
     */
    private function createPermissionsFile(string $modulePath, string $name): void
    {
        $permissionsPath = "{$modulePath}/permissions.php";
        $stub = <<<PHP
<?php

// This file is automatically discovered by the acl:sync-permissions command.
// Define permissions for the {$name} module here.
//
// Example:
// return [
//     'posts.view' => [
//         'description' => 'View posts',
//         'captype' => 'read', // Optional: 'read', 'write', 'manage'
//     ],
//     'posts.create' => [
//         'description' => 'Create new posts',
//         'captype' => 'write',
//     ],
// ];

return [
    //
];
PHP;
        file_put_contents($permissionsPath, $stub);
    }

    /**
     * Create the module's manifest file.
     */
    private function createModuleJson(string $modulePath, string $name): void
    {
        $jsonPath = "{$modulePath}/module.json";
        $content = [
            'name' => $name,
            'enabled' => true,
            'version' => '1.0.0',
            'providers' => [
                "Modules\\{$name}\\Providers\\{$name}ServiceProvider",
            ],
        ];
        file_put_contents($jsonPath, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
