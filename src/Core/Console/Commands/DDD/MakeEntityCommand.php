<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Create Domain Entity
 */
class MakeEntityCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'ddd:make-entity {module : The module name} {name : The entity name}';
    }

    public function description(): string
    {
        return 'Create a new domain entity in the specified module.';
    }

    public function handle(): int
    {
        $module = ucfirst($this->argument('module'));
        $name = ucfirst($this->argument('name'));

        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' does not exist.");
            return self::FAILURE;
        }

        $this->io->info("Creating entity [{$name}] in module [{$module}]...");

        $this->createEntity($modulePath, $module, $name);

        $this->io->success("Entity [{$name}] created successfully!");
        $this->io->comment("Next steps:");
        $this->io->listing([
            "1. Define entity properties and business rules",
            "2. Create value objects: php cli make:value-object {$module} YourValue",
            "3. Create repository interface: php cli ddd:make-repository {$module} {$name}",
            "4. Create Eloquent model: php cli make:model {$module} {$name}",
        ]);

        return self::SUCCESS;
    }

    private function createEntity(string $modulePath, string $module, string $name): void
    {
        $path = "{$modulePath}/Domain/Entities/{$name}.php";
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Entity [{$name}] already exists.");
            return;
        }

        $stub = $this->getEntityStub($module, $name);
        file_put_contents($path, $stub);
        
        $this->io->text("  <fg=green>✓</> Created: Domain/Entities/{$name}.php");
    }

    private function getEntityStub(string $module, string $name): string
    {
        $namespace = "Modules\\{$module}\\Domain\\Entities";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

/**
 * {$name} Entity
 * 
 * Domain entity representing {$name}.
 * Contains business logic and rules.
 */
class {$name}
{
    private string \$id;
    private string \$name;
    private ?\DateTimeInterface \$createdAt = null;
    private ?\DateTimeInterface \$updatedAt = null;

    public function __construct(
        string \$id,
        string \$name
    ) {
        \$this->id = \$id;
        \$this->setName(\$name);
        \$this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Set name with validation
     */
    public function setName(string \$name): void
    {
        if (empty(\$name)) {
            throw new \InvalidArgumentException('{$name} name cannot be empty');
        }

        if (strlen(\$name) > 255) {
            throw new \InvalidArgumentException('{$name} name is too long');
        }

        \$this->name = \$name;
        \$this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): string
    {
        return \$this->id;
    }

    public function getName(): string
    {
        return \$this->name;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return \$this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return \$this->updatedAt;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => \$this->id,
            'name' => \$this->name,
            'created_at' => \$this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => \$this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
PHP;
    }
}

