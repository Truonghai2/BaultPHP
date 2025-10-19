<?php

namespace Core\Session;

use Core\Contracts\Session\SessionHandlerInterface;
use Core\Contracts\Session\SessionInterface;

class Store implements SessionInterface
{
    protected string $id;
    protected string $name;
    protected array $attributes = [];
    protected SessionHandlerInterface $handler;
    protected bool $started = false;
    protected FlashBag $flashBag;

    public function __construct(string $name, SessionHandlerInterface $handler, ?string $id = null)
    {
        $this->name = $name;
        $this->handler = $handler;
        $this->setId($id ?? $this->generateSessionId());
    }

    public function start(): bool
    {
        $this->loadSession();
        if (!$this->has('_token')) {
            $this->regenerateToken();
        }
        $this->started = true;
        return true;
    }

    protected function loadSession(): void
    {
        $data = $this->handler->read($this->getId());
        $this->attributes = $data ? @unserialize($data) : [];

        if (!is_array($this->attributes)) {
            $this->attributes = [];
        }

        if (!isset($this->attributes['_flash']) || !is_array($this->attributes['_flash'])) {
            $this->attributes['_flash'] = [];
        }

        $this->flashBag = new FlashBag($this->attributes['_flash']);
        $this->attributes['_flash'] = [];
    }

    public function save(): void
    {
        // Get all new flashes and prepare them for the next request.
        $this->attributes['_flash'] = $this->flashBag->all();
        $this->handler->write($this->getId(), serialize($this->attributes));
        $this->started = false;
    }

    public function all(): array
    {
        return $this->attributes;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    public function flush(): void
    {
        $this->attributes = [];
    }

    public function regenerate(bool $destroy = false): bool
    {
        if ($destroy) {
            $this->handler->destroy($this->getId());
        }
        $this->setId($this->generateSessionId());
        return true;
    }

    public function invalidate(): bool
    {
        $this->flush();
        return $this->regenerate(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(20));
    }

    /**
     * Lấy token CSRF từ session.
     */
    public function token(): ?string
    {
        return $this->get('_token');
    }

    /**
     * Tạo mới token CSRF.
     */
    public function regenerateToken(): void
    {
        $this->set('_token', bin2hex(random_bytes(20)));
    }

    /**
     * Flash một giá trị vào session.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->flashBag->add($key, $value);
    }

    /**
     * Lấy một giá trị flash từ session.
     */
    public function getFlash(string $key, array $default = []): array
    {
        return $this->flashBag->get($key, $default);
    }

    /**
     * Lấy tất cả dữ liệu flash.
     */
    public function getFlashBag(): FlashBag
    {
        return $this->flashBag;
    }

    /**
     * Giữ lại dữ liệu flash từ request trước cho request tiếp theo.
     */
    public function reflash(): void
    {
        foreach ($this->flashBag->all() as $key => $messages) {
            $this->flashBag->set($key, $messages);
        }
    }

    /**
     * Lưu input của request vào session để sử dụng lại.
     */
    public function flashInput(array $input): void
    {
        $this->flash('_old_input', $input);
    }
}
