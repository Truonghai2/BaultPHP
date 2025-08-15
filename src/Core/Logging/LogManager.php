<?php

namespace Core\Logging;

use Core\Application;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sentry\SentrySdk;

/**
 * Quản lý việc tạo và truy xuất các kênh log (log channels).
 * Lớp này hoạt động như một nhà máy (factory) cho các logger,
 * dựa trên cấu hình trong `config/logging.php`.
 */
class LogManager implements LoggerInterface
{
    /**
     * The application instance.
     *
     * @var \Core\Application
     */
    protected Application $app;

    /**
     * Mảng cache các kênh log đã được khởi tạo.
     *
     * @var array<string, \Psr\Log\LoggerInterface>
     */
    protected array $channels = [];

    /**
     * The processors that should be pushed to all channels.
     *
     * @var callable[]
     */
    protected array $processors = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Lấy một instance logger cho một kênh cụ thể.
     */
    public function channel(?string $name = null): LoggerInterface
    {
        return $this->get($name ?? $this->getDefaultDriver());
    }

    /**
     * Lấy logger từ cache hoặc tạo mới nếu chưa có.
     */
    protected function get(string $name): LoggerInterface
    {
        if (!isset($this->channels[$name])) {
            $this->channels[$name] = $this->resolve($name);
        }

        return $this->channels[$name];
    }

    /**
     * Tạo một instance logger mới dựa trên cấu hình.
     */
    protected function resolve(string $name): LoggerInterface
    {
        $config = $this->configurationFor($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Kênh log [{$name}] chưa được định nghĩa.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (!method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$config['driver']}] không được hỗ trợ.");
        }

        $logger = $this->{$driverMethod}($config);

        // Push all global processors to the logger.
        foreach ($this->processors as $processor) {
            $logger->pushProcessor($processor);
        }

        return $logger;
    }

    /**
     * Tạo một logger ghi vào một file duy nhất.
     */
    protected function createSingleDriver(array $config): LoggerInterface
    {
        $handler = new StreamHandler(
            $config['path'],
            $this->level($config),
        );

        return new Logger($this->channelName(), [$this->prepareHandler($handler, $config)]);
    }

    /**
     * Tạo một logger ghi vào các file theo ngày.
     */
    protected function createDailyDriver(array $config): LoggerInterface
    {
        $handler = new RotatingFileHandler(
            $config['path'],
            $config['days'] ?? 7,
            $this->level($config),
        );

        return new Logger($this->channelName(), [$this->prepareHandler($handler, $config)]);
    }

    /**
     * Tạo một logger "stack" để gửi log đến nhiều kênh khác.
     */
    protected function createStackDriver(array $config): LoggerInterface
    {
        $handlers = [];
        $channels = $config['channels'] ?? [];

        foreach ($channels as $channel) {
            // Lấy các handler từ kênh con
            $childLogger = $this->channel($channel);
            if ($childLogger instanceof Logger) {
                $handlers = array_merge($handlers, $childLogger->getHandlers());
            }
        }

        return new Logger($this->channelName(), $handlers);
    }

    /**
     * Tạo một logger ghi vào error_log của PHP.
     */
    protected function createErrorLogDriver(array $config): LoggerInterface
    {
        $handler = new ErrorLogHandler(
            ErrorLogHandler::OPERATING_SYSTEM,
            $this->level($config),
        );

        return new Logger($this->channelName(), [$this->prepareHandler($handler, $config)]);
    }

    /**
     * Tạo một logger ghi vào Slack.
     */
    protected function createSlackDriver(array $config): LoggerInterface
    {
        if (!isset($config['url'])) {
            throw new InvalidArgumentException('Slack webhook URL is not configured.');
        }

        $handler = new SlackWebhookHandler(
            $config['url'],
            $config['channel'] ?? null,
            $config['username'] ?? 'BaultPHP Log',
            $config['attachment'] ?? true,
            $config['emoji'] ?? ':boom:',
            $config['short'] ?? false,
            $config['context'] ?? true,
            $this->level($config),
        );

        return new Logger($this->channelName(), [$this->prepareHandler($handler, $config)]);
    }

    /**
     * Tạo một logger ghi vào syslog của hệ thống.
     */
    protected function createSyslogDriver(array $config): LoggerInterface
    {
        $handler = new SyslogHandler(
            $this->app->make('config')->get('app.name', 'baultphp'),
            $config['facility'] ?? LOG_USER,
            $this->level($config),
        );

        return new Logger($this->channelName(), [$this->prepareHandler($handler, $config)]);
    }

    /**
     * Tạo một logger tùy chỉnh sử dụng một Monolog handler bất kỳ.
     * Đây là một driver linh hoạt cho phép cấu hình handler trực tiếp.
     */
    protected function createMonologDriver(array $config): LoggerInterface
    {
        if (!isset($config['handler'])) {
            throw new InvalidArgumentException('Monolog handler is not configured for the custom driver.');
        }

        $handlerClass = $config['handler'];
        $handlerParams = $config['with'] ?? [];

        // Tự động thêm level vào tham số nếu handler có hỗ trợ
        $constructor = (new \ReflectionClass($handlerClass))->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->getName() === 'level') {
                    $handlerParams['level'] = $this->level($config);
                    break;
                }
            }
        }

        $handler = $this->app->make($handlerClass, $handlerParams);

        return new Logger($this->channelName(), [$this->prepareHandler($handler, $config)]);
    }

    /**
     * Tạo một logger gửi log đến Sentry.
     */
    protected function createSentryDriver(array $config): LoggerInterface
    {
        if (! class_exists(\Sentry\Monolog\Handler::class)) {
            throw new \LogicException('Please install the sentry/sentry package to use the Sentry driver.');
        }

        $handler = new \Sentry\Monolog\Handler(SentrySdk::getCurrentHub(), $this->level($config));
        $fingersCrossedHandler = new FingersCrossedHandler($handler, new ErrorLevelActivationStrategy($this->level($config)), 0, true, true, LogLevel::DEBUG);
        return new Logger($this->channelName(), [$fingersCrossedHandler]);
    }

    /**
     * Chuẩn bị handler với formatter mặc định.
     */
    protected function prepareHandler(\Monolog\Handler\HandlerInterface $handler, array $config = []): \Monolog\Handler\HandlerInterface
    {
        $handler->setFormatter(new LineFormatter(
            '[%datetime%] %channel%.%level_name%: %message% %context% %extra%' . PHP_EOL,
            'Y-m-d H:i:s',
            true,
            true,
        ));

        return $handler;
    }

    protected function level(array $config): int
    {
        $level = Logger::toMonologLevel($config['level'] ?? 'debug');

        // In Monolog 3+, toMonologLevel returns a \Monolog\Level enum. We need its integer value.
        return $level->value;
    }

    protected function configurationFor(string $name): ?array
    {
        return $this->app->make('config')->get("logging.channels.{$name}");
    }

    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('logging.default', 'stack');
    }

    protected function channelName(): string
    {
        return $this->app->make('config')->get('app.env', 'production');
    }

    /**
     * Push a processor to all channels.
     *
     * @param callable $processor
     * @return $this
     */
    public function pushProcessor(callable $processor): self
    {
        $this->processors[] = $processor;
        return $this;
    }

    // Các phương thức của LoggerInterface được ủy quyền cho kênh mặc định
    public function emergency($message, array $context = []): void
    {
        $this->channel()->emergency($message, $context);
    }
    public function alert($message, array $context = []): void
    {
        $this->channel()->alert($message, $context);
    }
    public function critical($message, array $context = []): void
    {
        $this->channel()->critical($message, $context);
    }
    public function error($message, array $context = []): void
    {
        $this->channel()->error($message, $context);
    }
    public function warning($message, array $context = []): void
    {
        $this->channel()->warning($message, $context);
    }
    public function notice($message, array $context = []): void
    {
        $this->channel()->notice($message, $context);
    }
    public function info($message, array $context = []): void
    {
        $this->channel()->info($message, $context);
    }
    public function debug($message, array $context = []): void
    {
        $this->channel()->debug($message, $context);
    }
    public function log($level, $message, array $context = []): void
    {
        $this->channel()->log($level, $message, $context);
    }

    public function __call(string $method, array $parameters)
    {
        return $this->channel()->{$method}(...$parameters);
    }
}
