<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use InvalidArgumentException;

class MakeMigrationCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @return string
     */
    public function signature(): string
    {
        return 'ddd:make-migration {name : The name of the migration (e.g., create_users_table)} {--module= : The module to create the migration in}';
    }

    /**
     * The console command description.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Create a new migration file in a module or globally.';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->option('module');

        // Validate the migration name format
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            $this->io->error('Invalid migration name. Please use snake_case format.');
            return self::FAILURE;
        }

        $path = $this->getMigrationPath($module);

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $fileName = date('Y_m_d_His') . '_' . $name . '.php';
        $fullPath = $path . '/' . $fileName;

        try {
            $stub = $this->createStub($name, $module);
        } catch (InvalidArgumentException $e) {
            $this->io->error($e->getMessage());
            return self::FAILURE;
        }

        file_put_contents($fullPath, $stub);

        $this->io->success("Migration [{$fullPath}] created successfully.");

        return self::SUCCESS;
    }

    /**
     * Get the full path to the migration directory.
     *
     * @param string|null $module
     * @return string
     */
    protected function getMigrationPath(?string $module): string
    {
        if ($module) {
            return base_path('Modules/' . ucfirst($module) . '/Infrastructure/Migrations');
        }

        return base_path('database/migrations');
    }

    /**
     * Get the appropriate migration stub content.
     *
     * @param string $name
     * @param string|null $module
     * @return string
     */
    protected function createStub(string $name, ?string $module): string
    {
        $tableName = null;

        // Determine which stub to use and parse the table name
        if (str_starts_with($name, 'create_') && str_ends_with($name, '_table')) {
            $stubPath = $this->getStubPath('create');
            $tableName = substr($name, 7, -6);
        } elseif (preg_match('/(add|update|delete|remove)_.*_to_(.*)_table$/', $name, $matches)) {
            $stubPath = $this->getStubPath('update');
            $tableName = $matches[2];
        } else {
            $stubPath = $this->getStubPath('plain');
        }

        $stubContent = file_get_contents($stubPath);
        if ($stubContent === false) {
            throw new InvalidArgumentException("Unable to read stub file at: {$stubPath}");
        }

        return $this->populateStub($stubContent, $tableName, $module);
    }

    /**
     * Get the path to a migration stub file.
     *
     * @param string $type
     * @return string
     */
    protected function getStubPath(string $type): string
    {
        $path = base_path("src/Core/Console/Commands/stubs/migration.{$type}.stub");
        if (!file_exists($path)) {
            // Create stubs directory if it doesn't exist
            $stubsDir = dirname($path);
            if (!is_dir($stubsDir)) {
                mkdir($stubsDir, 0755, true);
            }
            // You might want to create default stubs here if they don't exist
            throw new InvalidArgumentException("Stub file [migration.{$type}.stub] not found in {$stubsDir}.");
        }
        return $path;
    }

    /**
     * Populate the stub with the dynamic data.
     *
     * @param string $stub
     * @param string|null $tableName
     * @param string|null $module
     * @return string
     */
    protected function populateStub(string $stub, ?string $tableName, ?string $module): string
    {
        $namespace = $module
            ? 'namespace Modules\\' . ucfirst($module) . '\\Infrastructure\\Migrations;'
            : '';

        $replacements = [
            '{{ namespace }}' => $namespace ? $namespace . "\n" : '',
            '{{ table }}' => $tableName ?? '',
        ];

        // Also handle placeholders without spaces
        $replacements['{{namespace}}'] = $replacements['{{ namespace }}'];
        $replacements['{{table}}'] = $replacements['{{ table }}'];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }
}
