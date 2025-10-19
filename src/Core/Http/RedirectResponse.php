<?php

namespace Core\Http;

use Core\Contracts\Session\SessionInterface;
use Core\Validation\ValidationException;
use Core\Validation\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * A custom RedirectResponse that integrates with the session to flash data.
 * This is useful for redirecting with input and errors after form submissions.
 * It is PSR-7 compliant by extending the base Response class.
 */
class RedirectResponse extends Response implements ResponseInterface
{
    /**
     * The session instance.
     *
     * @var \Core\Contracts\Session\SessionInterface|null
     */
    protected ?SessionInterface $session = null;

    /**
     * Create a redirect response.
     *
     * Produces a redirect response with a Location header and the given status
     * (302 by default).
     *
     * @param string|UriInterface $uri URI for the Location header.
     * @param int $status Integer status code for the redirect; 302 by default.
     * @param array $headers Array of headers to use at initialization.
     */
    public function __construct($uri, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        $this->headers['Location'] = (string) $uri;
    }

    /**
     * Sets the session instance on the response.
     *
     * @param  \Core\Contracts\Session\SessionInterface  $session
     * @return $this
     */
    public function setSession(SessionInterface $session): self
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Flash a piece of data to the session.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function with(string $key, $value): self
    {
        if (! $this->session) {
            throw new \RuntimeException('Session has not been set on the RedirectResponse.');
        }

        $this->session->getFlashBag()->set($key, $value);

        return $this;
    }

    /**
     * Flash an array of input to the session.
     *
     * @param  array  $input
     * @return $this
     */
    public function withInput(array $input): self
    {
        if (! $this->session) {
            throw new \RuntimeException('Session has not been set on the RedirectResponse.');
        }

        $this->session->getFlashBag()->set('_old_input', $input);

        return $this;
    }

    /**
     * Flash a container of errors to the session.
     *
     * @param  object|array  $provider The error provider (e.g., a Validator instance) or an array of errors.
     * @return $this
     */
    public function withErrors($provider): self
    {
        if (! $this->session) {
            throw new \RuntimeException('Session has not been set on the RedirectResponse.');
        }

        $errors = $this->parseErrors($provider);

        $this->session->getFlashBag()->set('errors', $errors);

        return $this;
    }

    /**
     * Parse the error provider into an array.
     *
     * This is where the original error likely occurred. The fix is to ensure
     * we check if the provider is an object before attempting to call a method on it.
     *
     * @param  object|array  $provider
     * @return array
     */
    protected function parseErrors($provider): array
    {
        // Handle the framework's specific validation classes first.
        if ($provider instanceof ValidationException) {
            return $provider->errors();
        }

        // The provider might also be the Validator instance itself.
        if ($provider instanceof Validator) {
            return $provider->errors();
        }

        // Keep original logic for compatibility.
        if (is_object($provider) && method_exists($provider, 'getMessageBag')) {
            return $provider->getMessageBag()->toArray();
        }

        if (is_object($provider) && method_exists($provider, 'toArray')) {
            return $provider->toArray();
        }

        return (array) $provider;
    }
}
