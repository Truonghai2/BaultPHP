<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class MakePolicyCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'ddd:make-policy {module : The name of the module.} {model : The name of the model.}';
    }

    public function description(): string
    {
        return 'Create a new policy class for a given model in a module.';
    }

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $modelName = $this->argument('model');

        $modulePath = base_path('Modules/' . $moduleName);

        if (!is_dir($modulePath)) {
            $this->io->error("Module [{$moduleName}] does not exist.");
            return self::FAILURE;
        }

        $policyName = $modelName . 'Policy';
        $policyPath = $modulePath . '/Application/Policies/' . $policyName . '.php';

        if (file_exists($policyPath)) {
            $this->io->error("Policy [{$policyName}] already exists.");
            return self::FAILURE;
        }

        // Đảm bảo thư mục tồn tại
        if (!is_dir(dirname($policyPath))) {
            mkdir(dirname($policyPath), 0755, true);
        }

        // Tìm FQCN của Model
        $modelFqcn = $this->findModelFqcn($moduleName, $modelName);
        if (!$modelFqcn) {
            $this->io->error("Model [{$modelName}] not found in module [{$moduleName}].");
            $this->io->comment("Looked in: Modules/{$moduleName}/Models/ and Modules/{$moduleName}/Infrastructure/Models/");
            return self::FAILURE;
        }

        // Lấy nội dung stub
        $stub = file_get_contents(base_path('src/Core/Console/Commands/stubs/policy.stub'));

        // Thay thế các placeholder
        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ model_import }}',
                '{{ class_name }}',
                '{{ model_name }}',
                '{{ model_variable }}',
            ],
            [
                "Modules\\{$moduleName}\\Application\\Policies",
                "use {$modelFqcn};",
                $policyName,
                $modelName,
                lcfirst($modelName),
            ],
            $stub,
        );

        // Ghi file
        file_put_contents($policyPath, $content);

        $this->io->success("Policy [{$policyName}] created successfully in module [{$moduleName}].");
        $this->io->comment("Remember to register it in your module's Service Provider, for example in CmsServiceProvider:");
        $this->io->writeln("  \$accessControl->policy({$modelName}::class, {$policyName}::class);");

        return self::SUCCESS;
    }

    /**
     * Find the Fully Qualified Class Name (FQCN) for a model within a module.
     * It checks common locations for models.
     */
    private function findModelFqcn(string $moduleName, string $modelName): ?string
    {
        $locations = [
            // Ví dụ: Modules/Cms/Models/Page.php
            "Modules\\{$moduleName}\\Models\\{$modelName}" => base_path("Modules/{$moduleName}/Models/{$modelName}.php"),
            // Ví dụ: Modules/User/Infrastructure/Models/User.php
            "Modules\\{$moduleName}\\Infrastructure\\Models\\{$modelName}" => base_path("Modules/{$moduleName}/Infrastructure/Models/{$modelName}.php"),
        ];

        foreach ($locations as $fqcn => $path) {
            if (file_exists($path)) {
                return $fqcn;
            }
        }

        return null;
    }
}
