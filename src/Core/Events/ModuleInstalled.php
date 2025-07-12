<?php

namespace Core\Events;

class ModuleInstalled
{
    public string $module;
    public ?int $userId;

    public function __construct(string $module, ?int $userId = null)
    {
        $this->module = $module;
        $this->userId = $userId;
    }
}
