<?php

namespace Core\Foundation;

use Core\Contracts\StatefulService;

/**
 * Quản lý và thực thi việc reset trạng thái cho tất cả các service stateful.
 */
class StateResetter
{
    /**
     * @var iterable<StatefulService>
     */
    protected iterable $statefulServices;

    public function __construct(iterable $statefulServices)
    {
        $this->statefulServices = $statefulServices;
    }

    public function reset(): void
    {
        foreach ($this->statefulServices as $service) {
            $service->resetState();
        }
    }
}
