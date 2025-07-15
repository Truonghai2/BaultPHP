<?php

namespace Console;

use Core\Application;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class Kernel
{
    /**
     * The application implementation.
     */
    protected Application $app;

    /**
     * The Symfony Console application.
     */
    protected ConsoleApplication $console;

    /**
     * Create a new console kernel instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->console = new ConsoleApplication('BaultPHP Console', $this->app->version());

        // Đăng ký các command ở đây
        $this->registerCommands();
    }

    /**
     * Run the console application.
     */
    public function handle(ArgvInput $input, ConsoleOutput $output): int
    {
        return $this->console->run($input, $output);
    }

    protected function registerCommands(): void
    {
        // Logic để load các command từ các provider hoặc từ một thư mục
        // Ví dụ: $this->console->add(new \App\Console\Commands\MyCommand());
    }
}