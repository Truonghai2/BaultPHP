<?php

declare(strict_types=1);

namespace Core\Features;

use Core\Config;

/**
 * Manages the state of feature flags for the application.
 */
class FeatureManager
{
    /**
     * A cache of all loaded feature flags.
     *
     * @var array<string, bool>
     */
    protected array $features = [];

    public function __construct(Config $config)
    {
        // Load features from the configuration file.
        // In a more advanced implementation, this could load from a database or Redis.
        $this->features = $config->get('features', []);
    }

    /**
     * Check if a given feature is enabled.
     *
     * @param string $feature The name of the feature to check.
     * @return bool
     */
    public function isEnabled(string $feature): bool
    {
        // Default to false if the feature is not defined.
        if (!isset($this->features[$feature])) {
            return false;
        }

        return $this->features[$feature] === true;
    }
}
