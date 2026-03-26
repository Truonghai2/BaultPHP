<?php

declare(strict_types=1);

namespace App\Cache;

use Core\Cache\Repository;
use Core\Contracts\Cache\Store;
use Core\Module\Sandbox\ModulePermissionGate;
use Core\Module\Sandbox\SandboxedCacheStore;
use ReflectionClass;

/**
 * Cache manager that wraps the default store with SandboxedCacheStore when
 * module_sandbox.enabled is true, so module code runs with cache:read/cache:write enforcement.
 */
class SandboxAwareCacheManager extends AppCacheManager
{
    /** @var array<string, Repository> */
    private array $wrappedStores = [];

    public function __construct(
        $app,
        private readonly ModulePermissionGate $gate,
    ) {
        parent::__construct($app);
    }

    public function store($name = null): Store
    {
        $name = $name ?: $this->getDefaultDriver();
        if (isset($this->wrappedStores[$name])) {
            return $this->wrappedStores[$name];
        }
        $repository = parent::store($name);
        $innerStore = $this->getInnerStoreFromRepository($repository);
        if ($innerStore === null) {
            return $repository;
        }
        $this->wrappedStores[$name] = new Repository(
            new SandboxedCacheStore($innerStore, $this->gate),
        );
        return $this->wrappedStores[$name];
    }

    private function getInnerStoreFromRepository(Store $repository): ?Store
    {
        if (!$repository instanceof Repository) {
            return null;
        }
        try {
            $ref = new ReflectionClass($repository);
            $prop = $ref->getProperty('store');
            $prop->setAccessible(true);
            $inner = $prop->getValue($repository);
            return $inner instanceof Store ? $inner : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
