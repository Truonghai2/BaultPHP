<?php

namespace Core\Console;

use Core\Console\Contracts\BaseCommand;

class MakeRuleCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'make:rule {name : The name of the rule class}';
    }

    public function description(): string
    {
        return 'Create a new validation rule class';
    }

    public function fire(): void
    {
        $name = $this->argument('name');
        $path = app_path("Rules");

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $file = "{$path}/{$name}.php";

        if (file_exists($file)) {
            $this->io->error("Rule [{$name}] already exists!");
            return;
        }

        $stub = $this->getStub();
        $stub = str_replace('{{RuleName}}', $name, $stub);

        file_put_contents($file, $stub);

        $this->io->success("Rule [{$name}] created successfully.");
    }

    protected function getStub(): string
    {
        return <<<STUB
<?php

namespace App\Rules;

use Core\Contracts\Validation\Rule;

class {{RuleName}} implements Rule
{
    public function passes(string \$attribute, mixed \$value): bool
    {
        // TODO: Implement the validation logic here.
        return true;
    }

    public function message(): string
    {
        return 'The validation error message.';
    }
}
STUB;
    }
}