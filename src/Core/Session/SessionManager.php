<?php

namespace Core\Session;

use Core\Application;
use Core\Contracts\StatefulService;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class SessionManager implements StatefulService
{
    protected Application $app;
    protected array $config;

    protected ?\Closure $handlerFactory = null;
    /**
     * The current session instance.
     * This is cached for the duration of a single request.
     *
     * @var Session|null
     */
    protected ?Session $session = null;

    public function __construct(Application $app, ?callable $handlerFactory = null)
    {
        $this->app = $app;
        $this->config = $app->make('config')->get('session');
        $this->handlerFactory = $handlerFactory;
    }

    /**
     * Get the session instance for the current request.
     *
     * If a session instance does not exist, it will be created.
     *
     * @return Session
     */
    public function getSession(): Session
    {
        if ($this->session === null) {
            $this->session = $this->buildSession();
        }

        return $this->session;
    }

    /**
     * Reset the state of the session manager.
     *
     * This is called by the Swoole server after each request to prevent
     * session state from leaking into the next request. It nullifies the
     * cached session instance, forcing a new one to be created.
     */
    public function resetState(): void
    {
        $this->session = null;
    }

    protected function buildSession(): Session
    {
        if (!$this->handlerFactory) {
            throw new \RuntimeException('Session handler factory is not configured.');
        }

        $handler = call_user_func($this->handlerFactory);
        $storage = new NativeSessionStorage([], $handler);

        return new Session($storage);
    }
}
