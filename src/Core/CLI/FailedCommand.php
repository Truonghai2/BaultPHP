<?php

namespace Core\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * A special command that is registered when a real command fails to be instantiated.
 * It displays detailed error information to help with debugging.
 */
class FailedCommand extends Command
{
    private string $failedClass;
    private Throwable $exception;

    /**
     * FailedCommand constructor.
     *
     * @param string $failedClass The FQCN of the command that failed to load.
     * @param Throwable $exception The exception that was caught.
     */
    public function __construct(string $failedClass, Throwable $exception)
    {
        $this->failedClass = $failedClass;
        $this->exception = $exception;

        // We try to extract the intended command name from the class name for better UX.
        // e.g., 'App\Console\Commands\MyTestCommand' becomes 'my:test'.
        $name = 'error:' . strtolower(str_replace('Command', '', (new \ReflectionClass($failedClass))->getShortName()));

        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription("<error>Command '{$this->failedClass}' failed to load.</error>");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->error([
            'Command Initialization Failed!',
            "Could not instantiate the command: {$this->failedClass}",
        ]);

        $io->section('Exception Details');
        $io->writeln("<comment>{$this->exception->getMessage()}</comment>");
        $io->writeln("In <info>{$this->exception->getFile()}:{$this->exception->getLine()}</info>");
        $io->newLine();
        $io->writeln($this->exception->getTraceAsString());

        return Command::FAILURE;
    }
}
