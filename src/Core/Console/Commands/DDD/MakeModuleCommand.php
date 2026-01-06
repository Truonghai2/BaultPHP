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
            // Application Layer (CQRS)
            'Application/Commands',
            'Application/CommandHandlers',
            'Application/Queries',
            'Application/QueryHandlers',
            'Application/Services',
            'Application/Policies',
            'Application/Listeners',
            'Application/Jobs',

            // Domain Layer (DDD)
            'Domain/Entities',
            'Domain/ValueObjects',
            'Domain/Events',
            'Domain/Repositories',
            'Domain/Services',
            'Domain/Exceptions',

            // Event Sourcing (Optional but ready)
            'Domain/Aggregates',
            'Domain/Aggregates/Events',

            // Infrastructure Layer
            'Infrastructure/Models',
            'Infrastructure/Repositories',
            'Infrastructure/Migrations',
            'Infrastructure/Observers',

            // HTTP Layer
            'Http/Controllers',
            'Http/Requests',
            'Http/Middleware',

            // Presentation
            'Providers',
            'Console',
            'config',
            'resources/views',
        ];

        foreach ($directories as $dir) {
            $fullPath = $modulePath . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
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
        $this->createEventSourcingConfig($modulePath, $name);
        $this->createReadme($modulePath, $name);
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

    /**
     * Create Event Sourcing configuration file
     */
    private function createEventSourcingConfig(string $modulePath, string $name): void
    {
        $configPath = "{$modulePath}/config/event-sourcing.php";
        $stub = <<<PHP
<?php

/**
 * {$name} Module - Event Sourcing Configuration
 * 
 * Module-specific event sourcing settings.
 * Overrides global config from config/event-sourcing.php
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Event Sourcing for {$name} Module
    |--------------------------------------------------------------------------
    */
    'enabled' => env('EVENT_SOURCING_{$name}_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Dual Write Mode
    |--------------------------------------------------------------------------
    */
    'dual_write' => env('EVENT_SOURCING_{$name}_DUAL_WRITE', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Record Events
    |--------------------------------------------------------------------------
    */
    'auto_record' => [
        'enabled' => env('EVENT_SOURCING_{$name}_AUTO_RECORD', false),
        
        'models' => [
            // 'Modules\\{$name}\\Infrastructure\\Models\\YourModel',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregates Configuration
    |--------------------------------------------------------------------------
    */
    'aggregates' => [
        // Example:
        // 'your_aggregate' => [
        //     'enabled' => true,
        //     'class' => 'Modules\\{$name}\\Domain\\Aggregates\\YourAggregate',
        //     'snapshots' => [
        //         'enabled' => true,
        //         'frequency' => 100,
        //     ],
        //     'observer' => 'Modules\\{$name}\\Infrastructure\\Observers\\YourObserver',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Projections (Optional)
    |--------------------------------------------------------------------------
    */
    'projections' => [
        'enabled' => false,
        'registered' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Commands
    |--------------------------------------------------------------------------
    */
    'commands' => [
        // 'Modules\\{$name}\\Console\\YourEventSourcingCommand',
    ],
];
PHP;
        file_put_contents($configPath, $stub);
    }

    /**
     * Create module README file
     */
    private function createReadme(string $modulePath, string $name): void
    {
        $readmePath = "{$modulePath}/README.md";
        $stub = <<<MD
# {$name} Module

## Overview

This module was generated using the DDD architecture pattern with Event Sourcing support.

## Structure

```
{$name}/
├── Application/          # Application Layer (Use Cases)
│   ├── Commands/         # CQRS Commands
│   ├── CommandHandlers/  # Command Handlers
│   ├── Queries/          # CQRS Queries  
│   ├── QueryHandlers/    # Query Handlers
│   ├── Services/         # Application Services
│   ├── Policies/         # Authorization Policies
│   └── Jobs/             # Background Jobs
│
├── Domain/               # Domain Layer (Business Logic)
│   ├── Entities/         # Domain Entities
│   ├── ValueObjects/     # Value Objects
│   ├── Aggregates/       # Event Sourcing Aggregates
│   ├── Events/           # Domain Events
│   ├── Repositories/     # Repository Interfaces
│   ├── Services/         # Domain Services
│   └── Exceptions/       # Domain Exceptions
│
├── Infrastructure/       # Infrastructure Layer
│   ├── Models/           # Eloquent Models
│   ├── Repositories/     # Repository Implementations
│   ├── Migrations/       # Database Migrations
│   └── Observers/        # Model Observers
│
├── Http/                 # Presentation Layer
│   ├── Controllers/      # HTTP Controllers
│   ├── Requests/         # Form Requests
│   └── Middleware/       # HTTP Middleware
│
├── Providers/            # Service Providers
├── Console/              # Console Commands
├── config/               # Configuration Files
└── resources/            # Views & Assets
    └── views/
```

## Quick Start

### 1. Create Entity & Model

\`\`\`bash
# Create entity
php cli ddd:make-entity {$name} YourEntity

# Create model
php cli make:model {$name} YourModel
\`\`\`

### 2. Create Repository

\`\`\`bash
php cli ddd:make-repository {$name} YourEntity
\`\`\`

### 3. Create Use Cases (CQRS)

\`\`\`bash
# Create command & handler
php cli ddd:make-use-case {$name} CreateYourEntity

# This creates:
# - Application/Commands/CreateYourEntityCommand.php
# - Application/CommandHandlers/CreateYourEntityHandler.php
\`\`\`

### 4. Event Sourcing (Optional)

\`\`\`bash
# Create aggregate
php cli make:aggregate {$name} YourAggregate

# Create domain event
php cli make:domain-event {$name} YourEventHappened

# Create aggregate service
php cli make:aggregate-service {$name} YourAggregate
\`\`\`

### 5. Create Controller

\`\`\`bash
php cli make:controller {$name} YourController
\`\`\`

## Configuration

### Enable Event Sourcing

Edit `config/event-sourcing.php`:

\`\`\`php
'enabled' => true,
'auto_record' => [
    'enabled' => true,
    'models' => [
        'Modules\\{$name}\\Infrastructure\\Models\\YourModel',
    ],
],
'aggregates' => [
    'your_aggregate' => [
        'enabled' => true,
        'class' => 'Modules\\{$name}\\Domain\\Aggregates\\YourAggregate',
        'observer' => 'Modules\\{$name}\\Infrastructure\\Observers\\YourObserver',
    ],
],
\`\`\`

### Define Permissions

Edit `permissions.php`:

\`\`\`php
return [
    '{strtolower($name)}.view' => [
        'description' => 'View {$name}',
        'captype' => 'read',
    ],
    '{strtolower($name)}.create' => [
        'description' => 'Create {$name}',
        'captype' => 'write',
    ],
];
\`\`\`

## Testing

\`\`\`bash
# Run module tests
php cli test --filter={$name}
\`\`\`

## Documentation

- [Event Sourcing Guide](../../docs/EVENT_SOURCING_IMPLEMENTATION_GUIDE.md)
- [Module Config Guide](../../docs/MODULE_CONFIG_GUIDE.md)
- [DDD Patterns](../../docs/FRAMEWORK_ANALYSIS_AND_INNOVATIONS.md)

## License

Same as main application.
MD;
        file_put_contents($readmePath, $stub);
    }
}
