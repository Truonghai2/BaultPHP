<?php

namespace Core\Features;

use Core\Config;
use Core\Contracts\StatefulService;

class FeatureManager implements StatefulService
{
    /**
     * The loaded feature flag configurations.
     *
     * @var array
     */
    protected array $features = [];

    /**
     * The cache for resolved feature values for the current request.
     *
     * @var array
     */
    protected array $retrieved = [];

    public function __construct(Config $config)
    {
        $this->features = $config->get('features.flags', []);
    }

    /**
     * Check if a given feature is enabled.
     *
     * @param string $feature
     * @return bool
     */
    public function isEnabled(string $feature): bool
    {
        if (isset($this->retrieved[$feature])) {
            return $this->retrieved[$feature];
        }

        $value = $this->features[$feature] ?? false;

        return $this->retrieved[$feature] = (bool) $value;
    }

    /**
     * Resets the retrieved feature cache after a request.
     */
    public function resetState(): void
    {
        $this->retrieved = [];
    }
}
