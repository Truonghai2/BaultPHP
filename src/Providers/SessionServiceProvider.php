<?php

namespace App\Providers;

use Core\Config;
use Core\Contracts\Session\SessionInterface;
use Core\Contracts\StatefulService;
use Core\Session\DirectSessionTokenStorage;
use Core\Session\NullSession;
use Core\Session\SessionManager;
use Core\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SessionManager::class);

        $this->app->tag(SessionManager::class, StatefulService::class);

        $this->app->singleton(RequestStack::class);

        $this->app->singleton('session', function ($app) {
            if (self::isResolvingSession($app)) {
                return new NullSession();
            }
            $config = new Config($app);
            $sessionManager = new SessionManager($app, $config);
            return $sessionManager->driver();
        });

        $this->app->alias('session', SessionInterface::class);

        $this->registerCsrfServices();

        $this->app->singleton(\Core\Security\CsrfManager::class, function ($app) {
            return new \Core\Security\CsrfManager(
                $app->make(CsrfTokenManagerInterface::class),
            );
        });
    }

    /**
     * Register the bindings for the Symfony CSRF component.
     */
    protected function registerCsrfServices(): void
    {
        $this->app->singleton(TokenGeneratorInterface::class, UriSafeTokenGenerator::class);

        // Register TokenStorageInterface as lazy singleton to avoid circular dependency
        // TokenStorageInterface -> DirectSessionTokenStorage -> session
        // This ensures TokenStorageInterface is not resolved during session resolution
        $this->app->singleton(TokenStorageInterface::class, function ($app) {
            return new DirectSessionTokenStorage($app);
        });

        // Register CsrfTokenManagerInterface as lazy singleton
        // CsrfTokenManagerInterface -> TokenStorageInterface -> session
        $this->app->singleton(CsrfTokenManagerInterface::class, function ($app) {
            return new CsrfTokenManager(
                $app->make(TokenGeneratorInterface::class),
                $app->make(TokenStorageInterface::class)
            );
        });
        $this->app->alias(CsrfTokenManagerInterface::class, CsrfTokenManager::class);
    }

    private static function isResolvingSession(\Core\Application $app): bool
    {
        try {
            $reflection = new \ReflectionClass($app);
            $prop = $reflection->getProperty('resolvingStack');
            $prop->setAccessible(true);
            /** @var array<int, string> $stack */
            $stack = $prop->getValue($app);
            return \in_array('session', $stack, true)
                || \in_array(SessionInterface::class, $stack, true);
        } catch (\Throwable) {
            return false;
        }
    }
}
