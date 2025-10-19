<?php

namespace Core\Http;

use Core\Application;
use Core\Contracts\Session\SessionInterface;
use Core\Routing\Router;

class Redirector
{
    protected Application $app;
    protected Router $router;
    protected SessionInterface $session;

    public function __construct(Application $app, Router $router, SessionInterface $session)
    {
        $this->app = $app;
        $this->router = $router;
        $this->session = $session;
    }

    /**
     * Create a new redirect response to the given path.
     *
     * @param string $path
     * @param int $status
     * @param array $headers
     * @return \Core\Http\RedirectResponse
     */
    public function to(string $path, int $status = 302, array $headers = []): RedirectResponse
    {
        return $this->createRedirect($path, $status, $headers);
    }

    /**
     * Create a new redirect response to the previous location.
     *
     * @param int $status
     * @param array $headers
     * @param string $fallback A literal path or a named route to use if the referer is not available.
     * @return \Core\Http\RedirectResponse
     */
    public function back(int $status = 302, array $headers = [], string $fallback = '/'): RedirectResponse
    {
        $previousUrl = $this->session->get('url.previous');

        if ($previousUrl) {
            return $this->to($previousUrl, $status, $headers);
        }

        $referer = $this->app->make(\Psr\Http\Message\ServerRequestInterface::class)->getHeaderLine('Referer');
        if ($referer) {
            return $this->to($referer, $status, $headers);
        }

        try {
            $url = $this->router->url($fallback);
        } catch (\Throwable) {
            $url = $fallback;
        }

        return $this->to($url, $status, $headers);
    }

    /**
     * Create a new redirect response to the "intended" location.
     * This is typically used after a successful login to redirect the user
     * back to the page they were trying to access.
     *
     * @param  string  $default
     * @param  int  $status
     * @param  array  $headers
     * @return \Core\Http\RedirectResponse
     */
    public function intended(string $default = '/', int $status = 302, array $headers = []): RedirectResponse
    {
        $path = $this->session->get('url.intended', $default);
        $this->session->remove('url.intended');

        return $this->to($path, $status, $headers);
    }

    /**
     * Create a new redirect response to a named route.
     */
    public function route(string $name, array $parameters = [], int $status = 302, array $headers = []): RedirectResponse
    {
        $url = $this->router->url($name, $parameters);
        return $this->to($url, $status, $headers);
    }

    protected function createRedirect(string $url, int $status, array $headers): RedirectResponse
    {
        $redirect = new RedirectResponse($url, $status, $headers);
        return $redirect->setSession($this->session);
    }
}
