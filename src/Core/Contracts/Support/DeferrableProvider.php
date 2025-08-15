<?php

namespace Core\Contracts\Support;

/**
 * Interface for service providers that can be deferred.
 */
interface DeferrableProvider
{
    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array;
}
