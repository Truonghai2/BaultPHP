<?php

namespace Core\Http;

use Nyholm\Psr7\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class RedirectResponse extends Response
{
    protected SessionInterface $session;

    /**
     * Create a new redirect response instance.
     *
     * @param string $url The URL to redirect to.
     * @param int $status The HTTP status code.
     * @param array $headers Additional headers.
     */
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        // Ensure Location header is set for redirection.
        $headers['Location'] = $url;
        parent::__construct($status, $headers);
    }

    /**
     * Set the session instance on the response.
     *
     * @param SessionInterface $session
     * @return $this
     */
    public function setSession(SessionInterface $session): static
    {
        $this->session = $session;
        return $this;
    }

    /**
     * Flash a piece of data to the session.
     *
     * @param string|array $key
     * @param mixed $value
     * @return $this
     */
    public function with($key, $value = null): static
    {
        $key = is_array($key) ? $key : [$key => $value];

        foreach ($key as $k => $v) {
            $this->session->getFlashBag()->set($k, $v);
        }

        return $this;
    }

    /**
     * Flash an array of input to the session.
     *
     * @param array $input
     * @return $this
     */
    public function withInput(array $input): static
    {
        return $this->with('_old_input', $input);
    }

    /**
     * Flash a container of errors to the session.
     *
     * @param array $errors
     * @return $this
     */
    public function withErrors(array $errors): static
    {
        return $this->with('errors', $errors);
    }
}
