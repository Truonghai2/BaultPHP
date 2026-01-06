<?php

declare(strict_types=1);

namespace Core\EventSourcing;

use Core\EventSourcing\Contracts\AggregateRoot;

abstract class BaseAggregateRoot implements AggregateRoot
{
    /** @var object[] */
    protected array $recordedEvents = [];

    /**
     * Aggregate ID
     */
    protected string $uuid;

    /**
     * Current version of the aggregate (for optimistic locking)
     */
    protected int $version = 0;

    abstract public function getAggregateRootId(): string;
    
    public function getRecordedEvents(): array
    {
        return $this->recordedEvents;
    }

    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }

    public static function reconstituteFromHistory(\Traversable $history): static
    {
        $instance = new static();
        
        foreach ($history as $event) {
            $instance->apply($event);
            $instance->version++;
        }

        $instance->clearRecordedEvents();
        return $instance;
    }

    protected function recordThat(object $event): void
    {
        $this->recordedEvents[] = $event;
        $this->apply($event);
    }

    public function apply(object $event): void
    {
        $method = $this->getApplyMethodName($event);

        if (method_exists($this, $method)) {
            $this->{$method}($event);
        }
    }

    private function getApplyMethodName(object $event): string
    {
        $classParts = explode('\\', get_class($event));

        return 'apply' . end($classParts);
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function incrementVersion(): void
    {
        $this->version++;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
