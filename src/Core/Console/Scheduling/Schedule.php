<?php

namespace Core\Console\Scheduling;

class Schedule
{
    /** @var Event[] */
    protected array $events = [];

    public function command(string $command): Event
    {
        $event = new Event($command);
        $this->events[] = $event;
        return $event;
    }

    public function call(\Closure $callback): Event
    {
        $event = new Event($callback);
        $this->events[] = $event;
        return $event;
    }

    /** @return Event[] */
    public function getEvents(): array
    {
        return $this->events;
    }
}
