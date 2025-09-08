<?php

namespace Core\Support\Facades;

use Core\Support\Facade;

/**
 * @method static bool isEnabled(string $feature)
 *
 * @see \Core\Features\FeatureManager
 */
class Feature extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'features';
    }
}
