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
    protected bool $dirty = false;
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

        $flashes = $this->pull('_flash', []);
        $this->flashBag = new FlashBag($flashes);
    }

    public function save(): void
    {
        $newFlashes = $this->flashBag->allNew();

        // Tối ưu hóa: Chỉ ghi vào storage nếu có dữ liệu session thay đổi hoặc có flash message mới.
        if (!$this->dirty && empty($newFlashes)) {
            return;
        }

        $this->attributes['_flash'] = $newFlashes;
        $this->handler->write($this->getId(), serialize($this->attributes));
        $this->dirty = false; // Reset dirty flag after saving
        // Don't set $this->started = false; session should remain started after save
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
        $this->dirty = true;
    }

    public function remove(string $key): void
    {
        unset($this->attributes[$key]);
        $this->dirty = true;
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
        $this->dirty = true;
    }

    public function regenerate(bool $destroy = false): bool
    {
        $oldId = $this->getId();
        
        if ($destroy) {
            $this->handler->destroy($oldId);
        }
        
        // Generate new session ID
        $this->setId($this->generateSessionId());
        $this->dirty = true;
        
        // Ensure session remains started after regeneration
        $this->started = true;
        
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
        $this->dirty = true;
    }

    /**
     * Flash một giá trị vào session.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->flashBag->add($key, $value);
        $this->dirty = true;
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
        if (!$this->isStarted()) {
            $this->start();
        }

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
        $this->dirty = true;
    }
}
