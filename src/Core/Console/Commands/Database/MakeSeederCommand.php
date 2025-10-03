<?php

namespace Core\Console\Commands\Database;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use InvalidArgumentException;

class MakeSeederCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:seeder {name : The name of the seeder class}';
    }

    public function description(): string
    {
        return 'Create a new database seeder class.';
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*Seeder$/', $name)) {
            $this->io->error('Invalid seeder name. Please use StudlyCase format and end with "Seeder" (e.g., UserSeeder).');
            return self::FAILURE;
        }

        $path = base_path('database/seeders');

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $file = "{$path}/{$name}.php";

        if (file_exists($file)) {
            $this->io->error("Seeder [{$name}] already exists!");
            return self::FAILURE;
        }

        try {
            $stub = file_get_contents($this->getStubPath());
        } catch (InvalidArgumentException $e) {
            $this->io->error($e->getMessage());
            return self::FAILURE;
        }

        $stub = str_replace('{{ClassName}}', $name, $stub);

        file_put_contents($file, $stub);

        $this->io->success("Seeder [database/seeders/{$name}.php] created successfully.");
        return self::SUCCESS;
    }

    /**
     * Get the path to the seeder stub file.
     */
    protected function getStubPath(): string
    {
        $path = base_path('src/Core/Console/Commands/stubs/seeder.stub');
        if (!file_exists($path)) {
            throw new InvalidArgumentException('Stub file [seeder.stub] not found.');
        }
        return $path;
    }
}
