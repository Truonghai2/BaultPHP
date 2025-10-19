<?php

namespace App\Providers;

use Core\Contracts\Session\SessionInterface;
use Core\Contracts\StatefulService;
use Core\Session\DirectSessionTokenStorage;
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
            return $app->make(SessionManager::class)->driver();
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

        $this->app->singleton(TokenStorageInterface::class, function ($app) {
            return new DirectSessionTokenStorage($app->make('session'));
        });

        $this->app->singleton(CsrfTokenManagerInterface::class, function ($app) {
            return new CsrfTokenManager($app->make(TokenGeneratorInterface::class), $app->make(TokenStorageInterface::class));
        });
        $this->app->alias(CsrfTokenManagerInterface::class, CsrfTokenManager::class);
    }
}
