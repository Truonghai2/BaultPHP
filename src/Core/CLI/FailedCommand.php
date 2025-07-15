<?php

namespace Core\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FailedCommand extends Command
{
    private string $className;
    private string $errorMessage;

    public function __construct(string $className, string $errorMessage)
    {
        $this->className = $className;
        $this->errorMessage = $errorMessage;

        // Đặt tên command dựa trên tên class bị lỗi để dễ nhận biết
        $commandName = 'error:' . strtolower(str_replace(['\\', '/'], ':', $className));
        parent::__construct($commandName);
    }

    protected function configure(): void
    {
        $this->setDescription("Hiển thị lỗi khi không thể tải command: {$this->className}");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<error>Không thể tải command '{$this->className}'.</error>");
        $output->writeln("<error>Lỗi: {$this->errorMessage}</error>");
        return Command::FAILURE;
    }
}