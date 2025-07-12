<?php

namespace Modules\User\Domain\ValueObjects;

class UserName
{
    private string $first_name;
    private string $last_name;

    public function __construct(string $first_name, string $last_name)
    {
        $this->first_name = $first_name;
        $this->last_name = $last_name;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function getLastName(): string
    {
        return $this->last_name;
    }

    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function equals(UserName $other): bool
    {
        return $this->first_name === $other->first_name &&
            $this->last_name === $other->last_name;
    }
}
