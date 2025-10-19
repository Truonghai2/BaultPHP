<?php

namespace Core\Session;

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

    public function __construct(private SessionInterface $session)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getToken(string $tokenId): string
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        $key = $this->getNamespace($tokenId);

        if (!$this->session->has($key)) {
            throw new TokenNotFoundException(\sprintf('The CSRF token with ID "%s" does not exist.', $tokenId));
        }

        return (string) $this->session->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function setToken(string $tokenId, #[\SensitiveParameter] string $token): void
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        $this->session->set($this->getNamespace($tokenId), $token);
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken(string $tokenId): bool
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        return $this->session->has($this->getNamespace($tokenId));
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken(string $tokenId): ?string
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        return $this->session->remove($this->getNamespace($tokenId));
    }

    /**
     * Gets the session namespace for a given token ID.
     */
    private function getNamespace(string $tokenId): string
    {
        return self::SESSION_NAMESPACE . '/' . $tokenId;
    }
}
