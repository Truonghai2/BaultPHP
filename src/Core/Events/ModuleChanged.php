<?php

namespace Core\Events;

/**
 * This event is dispatched whenever a module is added, updated, or deleted.
 */
class ModuleChanged
{
    /**
     * @var string The action performed on the module (e.g., 'added', 'updated', 'deleted').
     */
    public string $action;

    /**
     * @var string The name of the module that was changed.
     */
    public string $moduleName;

    /**
     * ModuleChanged constructor.
     *
     * @param string $action The action performed on the module (e.g., 'added', 'updated', 'deleted').
     * @param string $moduleName The name of the module that was changed.
     */
    public function __construct(string $action, string $moduleName)
    {
        $this->action = $action;
        $this->moduleName = $moduleName;
    }
}
