<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Application;
use Core\GraphQL\DataLoader;
use Core\GraphQL\FederationGateway;
use Core\Support\ServiceProvider;
use Psr\SimpleCache\CacheInterface;
use TheCodingMachine\GraphQLite\SchemaFactory;
use TheCodingMachine\GraphQLite\Context\Context;

/**
 * GraphQL Service Provider
 *
 * Registers GraphQLite and related services.
 */
class GraphQLServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerCache();
        $this->registerSchemaFactory();
        $this->registerDataLoader();
        $this->registerFederationGateway();
    }

    /**
     * Register cache for GraphQLite
     */
    protected function registerCache(): void
    {
        // GraphQLite requires a PSR-16 cache
        // We'll use the existing CacheInterface from CacheServiceProvider
        if (!$this->app->bound(\Psr\SimpleCache\CacheInterface::class)) {
            // Fallback to array cache if CacheServiceProvider not loaded
            $this->app->singleton(\Psr\SimpleCache\CacheInterface::class, function () {
                return new class implements \Psr\SimpleCache\CacheInterface {
                    private array $cache = [];

                    public function get(string $key, mixed $default = null): mixed
                    {
                        return $this->cache[$key] ?? $default;
                    }

                    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
                    {
                        $this->cache[$key] = $value;
                        return true;
                    }

                    public function delete(string $key): bool
                    {
                        unset($this->cache[$key]);
                        return true;
                    }

                    public function clear(): bool
                    {
                        $this->cache = [];
                        return true;
                    }

                    public function getMultiple(iterable $keys, mixed $default = null): iterable
                    {
                        $result = [];
                        foreach ($keys as $key) {
                            $result[$key] = $this->get($key, $default);
                        }
                        return $result;
                    }

                    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
                    {
                        foreach ($values as $key => $value) {
                            $this->set($key, $value, $ttl);
                        }
                        return true;
                    }

                    public function deleteMultiple(iterable $keys): bool
                    {
                        foreach ($keys as $key) {
                            $this->delete($key);
                        }
                        return true;
                    }

                    public function has(string $key): bool
                    {
                        return isset($this->cache[$key]);
                    }
                };
            });
        }
    }

    /**
     * Register GraphQLite SchemaFactory
     */
    protected function registerSchemaFactory(): void
    {
        $this->app->singleton(SchemaFactory::class, function ($app) {
            $cache = $app->make(\Psr\SimpleCache\CacheInterface::class);
            $config = $app->make('config');

            $factory = new SchemaFactory($cache, $app);

            // Configure namespaces from config
            $namespaces = $config->get('graphqlite.namespaces', []);

            // Add controller namespaces (for Query/Mutation)
            $controllerNamespaces = $namespaces['controllers'] ?? ['Modules\\'];
            foreach ($controllerNamespaces as $namespace) {
                $factory->addControllerNamespace($namespace);
            }

            // Add type namespaces
            $typeNamespaces = $namespaces['types'] ?? ['Modules\\'];
            foreach ($typeNamespaces as $namespace) {
                $factory->addTypeNamespace($namespace);
            }

            // Enable development mode if debug is on
            if ((bool) $config->get('app.debug', false)) {
                $factory->devMode();
            }

            return $factory;
        });

        // Register schema as singleton
        $this->app->singleton(\GraphQL\Type\Schema::class, function ($app) {
            $factory = $app->make(SchemaFactory::class);
            return $factory->createSchema();
        });
    }

    /**
     * Register DataLoader
     */
    protected function registerDataLoader(): void
    {
        $this->app->singleton(DataLoader::class, function ($app) {
            $config = $app->make('config');
            $enableCache = (bool) $config->get('graphql.dataloader.cache', true);
            $cacheTtl = (int) $config->get('graphql.dataloader.ttl', 3600);

            return new DataLoader($enableCache, $cacheTtl);
        });
    }

    /**
     * Register FederationGateway
     */
    protected function registerFederationGateway(): void
    {
        $this->app->singleton(FederationGateway::class, function ($app) {
            $config = $app->make('config');
            $federationConfig = $config->get('graphql.federation', []);

            return new FederationGateway($app, $federationConfig);
        });
    }
}
