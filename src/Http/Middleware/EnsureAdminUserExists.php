<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Contracts\Session\SessionInterface;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to ensure that at least one admin user exists in the system.
 * If no users exist, it redirects to the initial admin creation page.
 * This is a crucial step for the first-time setup of the application.
 */
class EnsureAdminUserExists implements MiddlewareInterface
{
    protected ?SessionInterface $session = null;

    /** @var bool|null Per-process cache for admin existence */
    protected static ?bool $adminExistsCache = null;

    public function __construct(
        protected Application $app
    ) {
    }

    /**
     * The URIs that should be accessible even if no admin user has been created.
     * This list should include the admin creation form and any related assets or APIs.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'setup/create-admin',
        'ping',
        'api*',
        'api/health',
        'metrics',
        'assets*',
    ];

    /**
     * Process an incoming server request and return a response.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Skip check for excepted routes early to avoid unnecessary cache/db operations
        if ($this->isExceptedRoute($request)) {
            return $handler->handle($request);
        }

        // Use fully qualified class name string to avoid triggering autoload
        // when the class doesn't exist
        $userClass = 'Modules\\User\\Infrastructure\\Models\\User';
        
        if (!class_exists($userClass, true)) {
            return $handler->handle($request);
        }

        if (static::$adminExistsCache === true) {
            return $handler->handle($request);
        }

        try {
            // Try to get cache manager, but handle circular dependency gracefully
            $cacheManager = null;
            try {
                $cacheManager = $this->app->make(\Core\Cache\CacheManager::class);
            } catch (\Throwable $e) {
                // If cache can't be resolved (e.g., circular dependency), skip caching
                // Fall back to direct database check
                if (strpos($e->getMessage(), 'Circular dependency') === false) {
                    // Only log non-circular dependency errors
                    if ($this->app->bound('log')) {
                        $this->app->make('log')->debug('EnsureAdminUserExists: Could not resolve cache', [
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $adminExists = false;
            if ($cacheManager !== null) {
                try {
                    $adminExists = $cacheManager->rememberForever('system.has_admin_user', function () use ($userClass) {
                        try {
                            $queryBuilder = call_user_func([$userClass, 'query']);
                            return $queryBuilder->limit(1)->count() > 0;
                        } catch (\Throwable $e) {
                            return false;
                        }
                    });
                } catch (\Throwable $e) {
                    if (strpos($e->getMessage(), 'Circular dependency') === false && $this->app->bound('log')) {
                        $this->app->make('log')->debug('EnsureAdminUserExists: Cache operation failed', [
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if ($adminExists === false) {
                try {
                    $queryBuilder = call_user_func([$userClass, 'query']);
                    $adminExists = $queryBuilder->limit(1)->count() > 0;
                } catch (\Throwable $e) {
                    $adminExists = false;
                }
            }

            if ($adminExists) {
                static::$adminExistsCache = true;
            } else {
                // Try to create RedirectResponse, fallback to basic Response if not available
                try {
                    if (class_exists(\Core\Http\RedirectResponse::class, true)) {
                        $redirect = new \Core\Http\RedirectResponse('/setup/create-admin');
                    } elseif (class_exists(\Core\Http\Response::class, true)) {
                        $redirect = new \Core\Http\Response('', 302, ['Location' => '/setup/create-admin']);
                    } else {
                        // Fallback to PSR-7 compatible response
                        $redirect = new \Laminas\Diactoros\Response\RedirectResponse('/setup/create-admin', 302);
                    }
                } catch (\Throwable $e) {
                    // If all else fails, use PSR-7 compatible response
                    $redirect = new \Laminas\Diactoros\Response\RedirectResponse('/setup/create-admin', 302);
                }
                
                // Only set session if redirect is RedirectResponse instance
                if ($redirect instanceof \Core\Http\RedirectResponse) {
                    if ($this->session === null && $this->app->bound('session')) {
                        try {
                            $this->session = $this->app->make('session');
                            $redirect->setSession($this->session);
                        } catch (\Throwable $e) {
                            if (strpos($e->getMessage(), 'Circular dependency') === false) {
                                if ($this->app->bound('log')) {
                                    $this->app->make('log')->debug('EnsureAdminUserExists: Could not resolve session', [
                                        'exception' => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                    }
                }

                return $redirect;
            }
        } catch (\Throwable $e) {   
            if (strpos($e->getMessage(), 'Circular dependency') === false) {
                if ($this->app->bound('log')) {
                    $this->app->make('log')->error('EnsureAdminUserExists: Failed to check admin user', [
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $handler->handle($request);
    }

    /**
     * Determine if the request has a URI that should be excluded from the check.
     * This method supports exact matches and wildcards (e.g., 'api/*').
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isExceptedRoute(ServerRequestInterface $request): bool
    {
        $path = trim($request->getUri()->getPath(), '/');

        foreach ($this->except as $except) {
            $except = trim($except, '/');
            if ($except !== '' && fnmatch($except, $path, FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }
}
