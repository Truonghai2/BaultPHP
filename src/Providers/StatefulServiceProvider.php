<?php

namespace App\Providers;

use Core\Contracts\StatefulService;
use Core\Foundation\StateResetter;
use Core\Support\ServiceProvider;
use Core\View\ViewFactory;

class StatefulServiceProvider extends ServiceProvider
{
    /**
     * List of stateful services that are not tagged in their own providers.
     * This acts as a fallback. Ideally, each service provider should be
     * responsible for tagging its own stateful services.
     *
     * @var string[]
     */
    protected array $statefulServices = [
        ViewFactory::class,
    ];

    public function register(): void
    {
        foreach ($this->statefulServices as $service) {
            $this->app->tag($service, StatefulService::class);
        }

        // Ensure StateResetter class is loaded before resolving
        if (!class_exists(StateResetter::class, true)) {
            throw new \RuntimeException("StateResetter class not found. Autoloader may not be initialized.");
        }

        $this->app->singleton(StateResetter::class, function ($app) {
            // Use getTaggedClasses() instead of getTagged() to avoid resolving during StateResetter creation
            $taggedServices = [];
            try {
                // Use reflection to check resolvingStack
                $reflection = new \ReflectionClass($app);
                $property = $reflection->getProperty('resolvingStack');
                $property->setAccessible(true);
                $resolvingStack = $property->getValue($app);
                
                // If StateResetter itself is in the resolving stack, return empty StateResetter
                // This prevents circular dependency when StateResetter depends on services
                // that depend on StateResetter
                if (in_array(StateResetter::class, $resolvingStack)) {
                    return new StateResetter([]);
                }
                
                $taggedClasses = $app->getTaggedClasses(StatefulService::class);
                foreach ($taggedClasses as $serviceClass) {
                    try {
                        // Skip if this service, session, or StateResetter is currently being resolved
                        if (in_array($serviceClass, $resolvingStack) || 
                            in_array('session', $resolvingStack) ||
                            in_array(\Core\Contracts\Session\SessionInterface::class, $resolvingStack) ||
                            in_array(StateResetter::class, $resolvingStack)) {
                            continue;
                        }
                        
                        $taggedServices[] = $app->make($serviceClass);
                    } catch (\ReflectionException $e) {
                        // If reflection fails, try to resolve anyway
                        try {
                            $taggedServices[] = $app->make($serviceClass);
                        } catch (\Core\Exceptions\ContainerException $e) {
                            // Skip services that cause circular dependency
                            if (strpos($e->getMessage(), 'Circular dependency') !== false) {
                                continue;
                            }
                            throw $e;
                        }
                    } catch (\Core\Exceptions\ContainerException $e) {
                        // Skip services that cause circular dependency
                        if (strpos($e->getMessage(), 'Circular dependency') !== false) {
                            continue;
                        }
                        throw $e;
                    }
                }
            } catch (\Throwable $e) {
                // If we can't get tagged services, create empty StateResetter
                // This prevents circular dependency issues
            }
            return new StateResetter($taggedServices);
        });

        $this->app->alias(StateResetter::class, 'state.resetter');
    }
}
