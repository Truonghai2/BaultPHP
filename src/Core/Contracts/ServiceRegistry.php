<?php 

namespace Core\Contracts;

class ServiceRegistry
{
    protected array $services = [];

    public function register(string $contract, object $implementation): void
    {
        $this->services[$contract] = $implementation;
    }

    public function get(string $contract): object
    {
        if (!isset($this->services[$contract])) {
            throw new \RuntimeException("Service not found: {$contract}");
        }
        return $this->services[$contract];
    }
}