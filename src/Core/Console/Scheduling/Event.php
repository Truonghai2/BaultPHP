<?php

namespace Core\Console\Scheduling;

use Core\Application;
use Cron\CronExpression;
use LogicException;

class Event
{
    protected string $command;
    protected \Closure $callback;
    public string $expression = '* * * * *';
    public string $description;

    public function __construct(string|\Closure $command)
    {
        if (is_string($command)) {
            $this->command = $command;
        } else {
            $this->callback = $command;
        }
    }

    public function run(Application $app): void
    {
        if (isset($this->callback)) {
            $app->call($this->callback);
            return;
        }

        if (isset($this->command)) {
            // A more robust implementation would parse the command and run it
            // through the console application to capture output, but passthru is simple.
            passthru(PHP_BINARY . ' ' . $app->basePath('cli') . ' ' . $this->command, $exitCode);
            return;
        }

        throw new LogicException('No command or callback has been set for the scheduled event.');
    }

    public function isDue(Application $app): bool
    {
        // We could inject a timezone from the app's config here
        return (new CronExpression($this->expression))->isDue();
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }
    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }
    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }
    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }
    public function dailyAt(string $time): self
    {
        $segments = explode(':', $time);
        return $this->cron("{$segments[1]} {$segments[0]} * * *");
    }
    public function twiceDaily(int $hour1 = 1, int $hour2 = 13): self
    {
        return $this->cron("0 {$hour1},{$hour2} * * *");
    }
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }
    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }
}
