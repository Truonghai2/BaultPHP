<?php 

namespace Core\Console\Contracts;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArgvInput;

abstract class BaseCommand implements CommandInterface
{
    protected SymfonyStyle $io;

    public function __construct()
    {
        $this->io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());
    }

    abstract public function signature(): string;
    abstract public function handle(array $args = []): void;
}
