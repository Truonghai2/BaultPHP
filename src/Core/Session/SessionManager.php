<?php

namespace Core\Session;

class SessionManager
{
    protected bool $isStarted = false;

    public function start(): void
    {
        if ($this->isStarted) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->isStarted = true;
    }

    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }
}