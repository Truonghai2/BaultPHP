<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeMailCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @return string
     */
    public function signature(): string
    {
        return 'ddd:make-mail {module} {name} {--queue : Create a queueable mailable class}';
    }

    /**
     * The console command description.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Create a new Mailable class within a module.';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $module = ucfirst($this->argument('module'));
        $name = $this->argument('name');

        if (!$module || !$name) {
            $this->io->error('You must provide a module and a mailable name. Example: `ddd:make-mail User WelcomeEmail`');
            return 1;
        }

        $path = base_path("Modules/{$module}/Application/Mail/{$name}.php");
        $directory = dirname($path);

        if (file_exists($path)) {
            $this->io->warning("Mailable class {$name} already exists in module {$module}.");
            return 0;
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $stub = $this->createStub($module, $name);

        file_put_contents($path, $stub);

        $this->io->success("Mailable [{$path}] created successfully.");

        return 0;
    }

    /**
     * Create the mailable class stub.
     *
     * @param string $module
     * @param string $name
     * @return string
     */
    protected function createStub(string $module, string $name): string
    {
        $isQueueable = $this->option('queue');
        $useStatements = 'use Core\\Mail\\Mailable;';
        $implements = '';

        if ($isQueueable) {
            $useStatements .= "\nuse Core\\Contracts\\Queue\\ShouldQueue;";
            $implements = ' implements ShouldQueue';
        }

        $viewName = strtolower($module) . '::emails.' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return <<<STUB
<?php

namespace Modules\\{$module}\\Application\\Mail;

{$useStatements}

class {$name} extends Mailable{$implements}
{
    /**
     * Create a new message instance.
     * Public properties on this class will be made available to the view.
     */
    public function __construct()
    {
        //
    }

    /**
     * Build the message.
     *
     * @return \$this
     */
    public function build()
    {
        return \$this->subject('Subject for {$name}')
                    ->view('{$viewName}');
    }
}
STUB;
    }
}
