<?php

namespace Core\Auth\Access;

class Response
{
    /**
     * The authorization decision.
     *
     * @var bool
     */
    protected bool $allowed;

    /**
     * The authorization message.
     *
     * @var string|null
     */
    protected ?string $message;

    /**
     * Create a new response.
     *
     * @param  bool  $allowed
     * @param  string|null  $message
     */
    public function __construct(bool $allowed, ?string $message = null)
    {
        $this->allowed = $allowed;
        $this->message = $message;
    }

    /**
     * Create a new "allow" response.
     */
    public static function allow(): self
    {
        return new static(true);
    }

    /**
     * Create a new "deny" response.
     */
    public static function deny(?string $message = null): self
    {
        return new static(false, $message);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }
    public function message(): ?string
    {
        return $this->message;
    }
}
