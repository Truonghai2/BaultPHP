<?php

namespace Core\Events;

/**
 * This event is dispatched whenever a module is added, updated, or deleted.
 */
class ModuleChanged
{
    public string $action;
    public string $moduleName;

    public function __construct(string $action, string $moduleName)
    {
        $this->action = $action;
        $this->moduleName = $moduleName;
    }
}
