<?php

namespace Core\Console\Contracts;

use Core\Console\Contracts\CommandInterface;
use Core\Console\Parser;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

abstract class BaseCommand implements CommandInterface
{
    /**
     * The console command I/O styler.
     */
    protected SymfonyStyle $io;

    /**
     * The input interface instance.
     */
    protected InputInterface $input;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        $this->io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());
    }

    /**
     * The entry point for the command.
     * This method parses the input and calls the fire() method.
     */
    public function handle(array $args = []): void
    {
        [$name, $arguments, $options] = Parser::parse($this->signature());

        $command = new SymfonyCommand($name);
        $command->setDefinition(array_merge($arguments, $options));

        // Prepend the command name to the args for ArgvInput to work correctly
        array_unshift($args, $name);
        $this->input = new ArgvInput($args);

        try {
            $this->input->bind($command->getDefinition());
            $this->input->validate();
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return;
        }

        $this->fire();
    }

    /**
     * The core logic of the command.
     * Child classes must implement this method.
     */
    abstract public function fire(): void;

    /**
     * Get an argument from the input.
     */
    protected function argument(string $key)
    {
        return $this->input->getArgument($key);
    }

    /**
     * Get an option from the input.
     */
    protected function option(string $key)
    {
        return $this->input->getOption($key);
    }

    /**
     * Get the description of the command.
     * Commands should override this method.
     */
    public function description(): string
    {
        return '';
    }
}