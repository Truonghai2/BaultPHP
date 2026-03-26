<?php

namespace Core\Session;

use Core\Application;
use Core\Contracts\Session\SessionInterface;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

/**
 * A CSRF token storage that interacts with the session service directly.
 *
 * This implementation is a workaround for architectures where CSRF tokens might be
 * accessed before the session is attached to the request by middleware. It fetches
 * the session singleton from the container and starts it on-demand.
 */
class DirectSessionTokenStorage implements TokenStorageInterface
{
    /**
     * The namespace used to store tokens in the session.
     */
    private const SESSION_NAMESPACE = '_csrf';

    private ?SessionInterface $session = null;

    public function __construct(
        private Application $app
    ) {
    }

    /**
     * Get the session instance, lazy loading it to avoid circular dependency.
     */
    private function getSession(): SessionInterface
    {
        if ($this->session === null) {
            // Lazy load session to avoid circular dependency
            // session -> TokenStorageInterface -> DirectSessionTokenStorage -> session
            if (!$this->app->bound('session')) {
                throw new \RuntimeException('Session service is not available.');
            }
            
            try {
                $this->session = $this->app->make('session');
            } catch (\Core\Exceptions\ContainerException $e) {
                // If circular dependency detected, throw a clearer exception
                // This should not happen if session is properly registered as lazy singleton
                if (strpos($e->getMessage(), 'Circular dependency') !== false) {
                    throw new \RuntimeException(
                        'Cannot resolve session: circular dependency detected. ' .
                        'This may occur if session is accessed during its own resolution. ' .
                        'Ensure session is only accessed after it has been fully resolved.',
                        0,
                        $e
                    );
                }
                throw $e;
            }
        }
        return $this->session;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken(string $tokenId): string
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $key = $this->getNamespace($tokenId);

        if (!$session->has($key)) {
            throw new TokenNotFoundException(\sprintf('The CSRF token with ID "%s" does not exist.', $tokenId));
        }

        return (string) $session->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function setToken(string $tokenId, #[\SensitiveParameter] string $token): void
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set($this->getNamespace($tokenId), $token);
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken(string $tokenId): bool
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session->has($this->getNamespace($tokenId));
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken(string $tokenId): ?string
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session->remove($this->getNamespace($tokenId));
    }

    /**
     * Gets the session namespace for a given token ID.
     */
    private function getNamespace(string $tokenId): string
    {
        return self::SESSION_NAMESPACE . '/' . $tokenId;
    }
}
