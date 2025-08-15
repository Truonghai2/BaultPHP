<?php

namespace Core\Events;

/**
 * ModuleInstalled event is triggered when a module is successfully installed.
 * It contains the module name and optionally the user ID of the installer.
 */
class ModuleInstalled
{
    /**
     * @var string The name of the installed module.
     */
    public string $module;

    /**
     * @var int|null The user ID of the installer, if applicable.
     */
    public ?int $userId;

    /**
     * ModuleInstalled constructor.
     *
     * @param string $module The name of the installed module.
     * @param int|null $userId The user ID of the installer, if applicable.
     */
    public function __construct(string $module, ?int $userId = null)
    {
        $this->module = $module;
        $this->userId = $userId;
    }
}
