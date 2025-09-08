<?php

namespace Core\Events\Queue;

use Core\Application;
use Core\Contracts\Queue\Job;
use Throwable;

/**
 * A dedicated, serializable job for handling queued event listeners.
 * This job resolves the actual listener from the container inside the queue worker,
 * ensuring dependencies are fresh and avoiding serialization issues.
 */
class CallQueuedListener implements Job
{
    /**
     * The class name of the listener.
     *
     * @var string
     */
    public string $class;

    /**
     * The event instance.
     *
     * @var object
     */
    public object $event;

    /**
     * The number of times the job may be attempted.
     *
     * @var int|null
     */
    public ?int $tries;

    /**
     * Create a new job instance.
     *
     * @param string $class
     * @param object $event
     * @param int|null $tries
     */
    public function __construct(string $class, object $event, ?int $tries = null)
    {
        $this->class = $class;
        $this->event = $event;
        $this->tries = $tries;
    }

    /**
     * Execute the job.
     * This method relies on the Queue Worker to perform method injection.
     *
     * @param \Core\Application $app
     * @return void
     */
    public function handle(Application $app): void
    {
        $listener = $app->make($this->class);
        $listener->handle($this->event);
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $e
     * @return void
     */
    public function failed(Throwable $e): void
    {
        $listener = app($this->class);

        if (method_exists($listener, 'failed')) {
            $listener->failed($this->event, $e);
        }
    }

    /**
     * Get the display name for the queued job.
     * This is used by the queue worker for logging.
     *
     * @return string
     */
    public function displayName(): string
    {
        return $this->class;
    }
}
