<?php

namespace Modules\User\Domain\Entities;

/**
 * This is a pure Domain Entity.
 * It holds business logic and state, but has no knowledge of the database.
 */
class User
{
    private ?int $id;
    private string $name;
    private string $email;
    private ?string $password; // Password can be null when fetching from DB without it

    public function __construct(?int $id, string $name, string $email, ?string $password = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}