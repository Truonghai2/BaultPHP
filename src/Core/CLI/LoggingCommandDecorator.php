<?php

namespace Core\CLI;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Một decorator "trong suốt" cho các Command của Symfony để thêm chức năng logging
 * trước và sau khi chúng thực thi. Nó bao bọc toàn bộ vòng đời `run` của một command.
 */
class LoggingCommandDecorator extends Command
{
    private Command $command;
    private LoggerInterface $logger;

    public function __construct(Command $command, LoggerInterface $logger)
    {
        $this->command = $command;
        $this->logger = $logger;

        // Decorator phải nhận tên của command mà nó bao bọc.
        parent::__construct($this->command->getName());
    }

    /**
     * Decorator phải thể hiện cùng một định nghĩa (arguments, options)
     * với command mà nó bao bọc để việc binding input hoạt động chính xác.
     */
    protected function configure(): void
    {
        $this->setDefinition($this->command->getDefinition());
        $this->setDescription($this->command->getDescription());
        $this->setHelp($this->command->getHelp());
        $this->setAliases($this->command->getAliases());
        $this->setHidden($this->command->isHidden());
    }

    /**
     * Ủy quyền việc set application cho cả decorator và command được bao bọc.
     */
    public function setApplication(Application $application = null): void
    {
        parent::setApplication($application);
        $this->command->setApplication($application);
    }

    /**
     * Cần thiết để chuyển tiếp các lệnh gọi đến các phương thức tùy chỉnh
     * trên BaseCommand của framework, ví dụ như `setCoreApplication`.
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->command, $method], $args);
    }

    /**
     * Chúng ta ghi đè `run` để bao bọc toàn bộ quá trình thực thi command với logging.
     * Cách này mạnh mẽ hơn việc ghi đè `execute` vì `run` là public
     * và quản lý toàn bộ vòng đời của command (binding, validation, ...).
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->command->getName();

        // Log này khớp với những gì đã thấy trong file log bạn cung cấp.
        $this->logger->info("Executing command: '{$commandName}'", [
            'arguments' => $input->getArguments(),
            'options'   => $input->getOptions(),
        ]);

        try {
            $exitCode = $this->command->run($input, $output);

            $this->logger->info("Command '{$commandName}' finished successfully with exit code: {$exitCode}.");

            return $exitCode;
        } catch (Throwable $e) {
            $this->logger->error("Command '{$commandName}' failed with an exception.", [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'message'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Ghi đè phương thức execute để tuân thủ yêu cầu của Symfony\Component\Console\Command.
     * Phương thức này không nên được gọi trực tiếp vì logic đã được xử lý trong `run`.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        throw new \LogicException('Decorator execute method should not be called directly.');
    }
}
