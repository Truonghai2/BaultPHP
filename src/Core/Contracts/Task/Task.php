<?php

namespace Core\Contracts\Task;

/**
 * Interface Task
 * Represents a unit of work that can be offloaded to a Swoole Task Worker.
 *
 * Note: Task objects must be serializable. Avoid closures or non-serializable resources.
 */
interface Task
{
    /**
     * Execute the task.
     * This method is called within the Task Worker process.
     *
     * @return mixed The result of the task, which will be passed to the onFinish event.
     */
    public function handle();
}
