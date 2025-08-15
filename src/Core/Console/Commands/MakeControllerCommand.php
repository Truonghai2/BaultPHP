<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeControllerCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'make:controller {module} {name} {--api : Create an API controller}';
    }
    public function description(): string
    {
        return 'Create a new controller in the specified module.';
    }

    public function handle(): int
    {
        $this->io->title('Creating Controller');
        $module = $this->argument('module');
        $name = $this->argument('name');
        $isApi = $this->option('api');
        if (empty($module) || empty($name)) {
            $this->io->error('Module and controller name are required.');
            return self::FAILURE;
        }
        $this->executeCommand($module, $name, $isApi);
        return self::SUCCESS;
    }

    /**
     * Executes the command to create a controller.
     */
    private function executeCommand(string $module, string $name, bool $isApi): void
    {
        $this->io->info("Creating controller '{$name}' in module '{$module}'...");

        $controllerName = ucfirst($name) . 'Controller';
        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            throw new \InvalidArgumentException("Module '{$module}' does not exist.");
        }
        $controllerPath = "{$modulePath}/Http/Controllers/{$controllerName}.php";
        $directory = dirname($controllerPath);
        if (file_exists($controllerPath)) {
            throw new \InvalidArgumentException("Controller [{$controllerName}] already exists in module [{$module}].");
        }
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $stub = $this->getStub($module, $controllerName, $isApi);
        file_put_contents($controllerPath, $stub);
        $this->io->success("Controller [{$controllerName}] created successfully in module [{$module}].");
    }

    /**
     * Returns the stub content for the controller.
     */
    private function getStub(string $moduleName, string $controllerName, bool $isApi): string
    {
        $namespace = "Modules\\{$moduleName}\\Http\\Controllers";

        if ($isApi) {
            return <<<PHP
<?php

namespace {$namespace};

use Http\JsonResponse;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class {$controllerName}
{
    public function index(): JsonResponse
    {
        //
    }

    public function store(Request \$request): JsonResponse
    {
        //
    }

    public function show(int \$id): JsonResponse
    {
        //
    }

    public function update(Request \$request, int \$id): JsonResponse
    {
        //
    }

    public function destroy(int \$id): JsonResponse
    {
        //
    }
}
PHP;
        }

        return <<<PHP
<?php

namespace {$namespace};

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class {$controllerName} extends Controller
{
    //
}
PHP;
    }
}
