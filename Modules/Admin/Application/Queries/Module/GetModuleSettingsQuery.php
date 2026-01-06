<?php

namespace Modules\Admin\Application\Queries\Module;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetModuleSettingsQuery
 * 
 * Query to retrieve settings for a specific module.
 */
class GetModuleSettingsQuery implements QueryInterface
{
    public function __construct(
        public readonly string $moduleName,
        public readonly ?string $group = null,
        public readonly ?bool $publicOnly = false
    ) {}

    public function getQueryName(): string
    {
        return 'admin.module.get_settings';
    }
}

