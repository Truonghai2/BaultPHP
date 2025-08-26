<?php

declare(strict_types=1);

namespace Core\Hashing;

use Core\Application;
use InvalidArgumentException;

class HashManager
{
    protected Application $app;

    /**
     * The array of created "drivers".
     *
     * @var array<string, \Core\Hashing\HasherInterface>
     */
    protected array $drivers = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a hash driver instance.
     */
    public function driver(?string $name = null): HasherInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    /**
     * Resolve the given driver.
     */
    protected function resolve(string $name): HasherInterface
    {
        $config = $this->app['config']["hashing.{$name}"];

        if (is_null($config)) {
            throw new InvalidArgumentException("Hash driver [{$name}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($name) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->$driverMethod($config);
        }

        throw new InvalidArgumentException("Hash driver [{$name}] is not supported.");
    }

    protected function createBcryptDriver(array $config): HasherInterface
    {
        return new BcryptHasher($config);
    }

    protected function createArgonDriver(array $config): HasherInterface
    {
        return new Argon2iHasher($config);
    }

    /**
     * Get information about the given hashed value.
     */
    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * Hash the given value.
     */
    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    /**
     * Check the given plain value against a hash.
     * This method intelligently determines the driver from the hash itself.
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        $info = $this->info($hashedValue);

        $driverName = match ($info['algoName'] ?? 'unknown') {
            'argon2i', 'argon2id' => 'argon',
            'bcrypt' => 'bcrypt',
            default => null,
        };

        return $driverName ? $this->driver($driverName)->check($value, $hashedValue, $options) : false;
    }

    /**
     * Check if the given hash has been hashed using the given options.
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    public function getDefaultDriver(): string
    {
        return $this->app['config']['hashing.driver'];
    }
}
