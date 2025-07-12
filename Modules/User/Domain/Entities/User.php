<?php

namespace Modules\User\Domain\Entities;

use Modules\User\Domain\ValueObjects\UserName;

class User
{
    private string $id;
    private UserName $name;
    private string $email;

    public function __construct(string $id, UserName $name, string $email)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
    }

    public function getName(): UserName
    {
        return $this->name;
    }
    
    public function getId(): string
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->name->getFirstName();
    }

    public function getLastName(): string
    {
        return $this->name->getLastName();
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
