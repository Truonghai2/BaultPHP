<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeModelCommand extends BaseCommand
{
    
    public function signature(): string
    {
        return 'make:model {module} {name}';
    }

    public function description(): string
    {
        return 'Create a new model in the specified module.';
    }

    
    public function handle(): int
    {
        $this->io->title('Creating Model');
        $module = $this->argument('module');
        $name = $this->argument('name');
        if (empty($module) || empty($name)) {
            $this->io->error('Module and model name are required.');
            return 1;
        }
        $this->fire();

        return 0;
    }

    /**
     * Executes the command to create a model.
     */
    public function fire(): void
    {
    
        $module = $this->argument('module');
        $name = $this->argument('name');
        $this->createModelClass($module, $name);
    }

    /**
     * Creates a new model class in the specified module.
     */
    private function createModelClass(string $module, string $name): void
    {
        $this->io->info("Creating model class: {$name} in module: {$module}");
        $modelName = ucfirst($name);
        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' does not exist.");
            return;
        }
        $path = "{$modulePath}/Infrastructure/Models/{$modelName}.php";
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        if (file_exists($path)) {
            $this->io->error("Model [{$modelName}] already exists in module [{$module}].");
            return;
        }

        $stub = $this->getStub($module, $modelName);
        file_put_contents($path, $stub);
        $this->io->success("Model [{$modelName}] created successfully in module [{$module}].");
    }

    /**
     * Generates the model class content from a stub.
     */
    private function getStub(string $moduleName, string $modelName): string
    {
        $namespace = "Modules\\{$moduleName}\\Infrastructure\\Models";
        $tableName = $this->generateTableName($modelName);

        return <<<PHP
<?php

namespace {$namespace};

use Core\ORM\Model;

class {$modelName} extends Model
{
    /**
     * The table associated with the model.
     */
    protected static string \$table = '{$tableName}';

    //
}
PHP;
    }

    /**
     * Converts a StudlyCase model name to a snake_case, plural table name.
     * Example: 'ProductCategory' becomes 'product_categories'.
     */
    private function generateTableName(string $modelName): string
    {
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));
        return $snakeCase . 's'; // Simple pluralization
    }
}