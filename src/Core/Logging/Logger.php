<?php

namespace Core\Logging;

class Logger
{
    protected string $logPath;

    public function __construct(string $logPath = null)
    {
        $this->logPath = $logPath ?? base_path('storage/logs');
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d');
        $time = date('H:i:s');

        $contextString = $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$time}] {$level}: {$message} {$contextString}\n";

        $file = "{$this->logPath}/{$date}.log";
        file_put_contents($file, $logMessage, FILE_APPEND);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }
}
