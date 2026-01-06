<?php

namespace Core\Console\Commands\DDD;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Create Value Object
 */
class MakeValueObjectCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:value-object {module : The module name} {name : The value object name}';
    }

    public function description(): string
    {
        return 'Create a new value object in the specified module.';
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

        $this->io->info("Creating value object [{$name}] in module [{$module}]...");

        $this->createValueObject($modulePath, $module, $name);

        $this->io->success("Value object [{$name}] created successfully!");
        $this->io->comment("Remember: Value objects are immutable and compared by value, not identity.");

        return self::SUCCESS;
    }

    private function createValueObject(string $modulePath, string $module, string $name): void
    {
        $path = "{$modulePath}/Domain/ValueObjects/{$name}.php";
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            $this->io->error("Value object [{$name}] already exists.");
            return;
        }

        $stub = $this->getValueObjectStub($module, $name);
        file_put_contents($path, $stub);
        
        $this->io->text("  <fg=green>✓</> Created: Domain/ValueObjects/{$name}.php");
    }

    private function getValueObjectStub(string $module, string $name): string
    {
        $namespace = "Modules\\{$module}\\Domain\\ValueObjects";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

/**
 * {$name} Value Object
 * 
 * Immutable value object representing {$name}.
 */
class {$name}
{
    private readonly string \$value;

    private function __construct(string \$value)
    {
        \$this->validate(\$value);
        \$this->value = \$value;
    }

    /**
     * Create from string
     */
    public static function fromString(string \$value): self
    {
        return new self(\$value);
    }

    /**
     * Validate value
     */
    private function validate(string \$value): void
    {
        if (empty(\$value)) {
            throw new \InvalidArgumentException('{$name} cannot be empty');
        }

        // Add more validation rules here
        // if (strlen(\$value) > 255) {
        //     throw new \InvalidArgumentException('{$name} is too long');
        // }
    }

    /**
     * Get value as string
     */
    public function toString(): string
    {
        return \$this->value;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return \$this->value;
    }

    /**
     * Check equality
     */
    public function equals({$name} \$other): bool
    {
        return \$this->value === \$other->value;
    }

    /**
     * Get value (alternative)
     */
    public function getValue(): string
    {
        return \$this->value;
    }
}
PHP;
    }
}

