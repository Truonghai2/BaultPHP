<?php

namespace Core\Debug;

use Core\Contracts\Session\SessionInterface;

/**
 * Session store wrapper với real-time broadcasting.
 */
class RealtimeTraceableSessionStore implements SessionInterface
{
    public function __construct(
        protected SessionInterface $session,
        protected DebugBroadcaster $broadcaster,
    ) {
    }

    public function start(): void
    {
        $this->session->start();
        $this->broadcaster->broadcastSession('START', 'session_id', $this->getId());
    }

    public function getId(): string
    {
        return $this->session->getId();
    }

    public function setId(?string $id): void
    {
        $this->session->setId($id);
    }

    public function getName(): string
    {
        return $this->session->getName();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->session->get($key, $default);
        $this->broadcaster->broadcastSession('GET', $key, $value);
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
        $this->broadcaster->broadcastSession('SET', $key, $value);
    }

    public function has(string $key): bool
    {
        return $this->session->has($key);
    }

    public function remove(string $key): void
    {
        $this->session->remove($key);
        $this->broadcaster->broadcastSession('REMOVE', $key);
    }

    public function all(): array
    {
        return $this->session->all();
    }

    public function regenerate(bool $destroy = false): bool
    {
        $result = $this->session->regenerate($destroy);
        $this->broadcaster->broadcastSession('REGENERATE', 'session_id', $this->getId());
        return $result;
    }

    public function invalidate(): bool
    {
        $result = $this->session->invalidate();
        $this->broadcaster->broadcastSession('INVALIDATE', 'session_id');
        return $result;
    }

    public function save(): void
    {
        $this->session->save();
        $this->broadcaster->broadcastSession('SAVE', 'session_id', $this->getId());
    }

    public function flash(string $key, mixed $value): void
    {
        $this->session->flash($key, $value);
        $this->broadcaster->broadcastSession('FLASH', $key, $value);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->session->getFlash($key, $default);
    }

    public function reflash(): void
    {
        $this->session->reflash();
        $this->broadcaster->broadcastSession('REFLASH', 'all_flash');
    }

    public function keep(array $keys): void
    {
        $this->session->keep($keys);
    }

    public function getFlashBag(): object
    {
        return $this->session->getFlashBag();
    }

    public function forget(string|array $keys): void
    {
        $this->session->forget($keys);
        $keysArray = is_array($keys) ? $keys : [$keys];
        foreach ($keysArray as $key) {
            $this->broadcaster->broadcastSession('FORGET', $key);
        }
    }

    public function flush(): void
    {
        $this->session->flush();
        $this->broadcaster->broadcastSession('FLUSH', 'all_keys');
    }

    public function isStarted(): bool
    {
        return $this->session->isStarted();
    }

    public function token(): string
    {
        return $this->session->token();
    }

    public function regenerateToken(): void
    {
        $this->session->regenerateToken();
        $this->broadcaster->broadcastSession('REGENERATE_TOKEN', '_token', $this->token());
    }
}
