<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeModuleCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'ddd:make-module {name}';
    }

    public function description(): string
    {
        return 'Create a new module with DDD structure';
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (!$name) {
            // This check is now redundant as Symfony will enforce the required argument.
            // Kept here for clarity, but can be removed.
            $this->io->error('Module name is required.');
            return 1; // Return a non-zero exit code for failure
        }

        $name = ucfirst($name);
        $basePath = base_path("Modules/{$name}");

        if (is_dir($basePath)) {
            $this->io->error("Module '{$name}' already exists at {$basePath}.");
            return 1;
        }

        $basePath = __DIR__ . '/../../../Modules/' . $name;
        $structure = [
            '/Application/DTOs',
            '/Application/UseCases/Commands',
            '/Application/UseCases/Queries',
            '/Domain/Entities',
            '/Domain/Events',
            '/Domain/Repositories',
            '/Domain/Services',
            '/Domain/ValueObjects',
            '/Http/Controllers',
            '/Http/Middleware',
            '/Infrastructure/Models',
            '/Infrastructure/Repositories',
            '/Infrastructure/Migrations',
            '/Providers',
            '/Views',
        ];

        foreach ($structure as $dir) {
            $fullPath = $basePath . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
        }

        file_put_contents("{$basePath}/Http/routes.php", "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::prefix('" . strtolower($name) . "')->group(function () {\n    // Route của module {$name}\n});");

        file_put_contents("{$basePath}/config.php", "<?php\n\nreturn [\n    // cấu hình {$name}\n];");

        file_put_contents("{$basePath}/events.php", "<?php\n\nreturn [\n    // 'Event\\Class' => [Listener\\Class]\n];");

        file_put_contents("{$basePath}/permissions.php", "<?php\n\nreturn [\n    // 'permission_name',\n];");

        file_put_contents("{$basePath}/module.json", json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'providers' => ["Modules\\{$name}\\Providers\\ModuleServiceProvider"]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $providerStub = "<?php\n\nnamespace Modules\\{$name}\\Providers;\n\nuse Core\\BaseServiceProvider;\n\nclass ModuleServiceProvider extends BaseServiceProvider\n{\n    public function register(): void\n    {\n        parent::register();\n    }\n}\n";
        file_put_contents("{$basePath}/Providers/ModuleServiceProvider.php", $providerStub);

        $this->io->success("Module {$name} with DDD structure created successfully.");
        return 0; // Return 0 for success
    }
}