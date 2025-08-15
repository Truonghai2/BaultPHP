<?php

namespace Core\Contracts;

/**
 * ServiceRegistry is responsible for managing the application's service container.
 * It allows for registering and retrieving services by their contract/interface.
 */
class ServiceRegistry
{
    protected array $services = [];

    /**
     * Register a service implementation for a given contract.
     *
     * @param string $contract The contract/interface name.
     * @param object $implementation The service implementation.
     * @return void
     */
    public function register(string $contract, object $implementation): void
    {
        $this->services[$contract] = $implementation;
    }

    /**
     * Retrieve a service implementation by its contract.
     *
     * @param string $contract The contract/interface name.
     * @return object The service implementation.
     * @throws \RuntimeException If the service is not found.
     */
    public function get(string $contract): object
    {
        if (!isset($this->services[$contract])) {
            throw new \RuntimeException("Service not found: {$contract}");
        }
        return $this->services[$contract];
    }
}
