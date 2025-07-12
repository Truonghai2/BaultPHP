<?php

namespace Modules\User\Infrastructure\Models;

use Core\Contracts\Auth\Authenticatable;
use Core\ORM\Model;

class User extends Model implements Authenticatable
{
    protected string $table = 'users';
    
    protected $fillable = ['first_name', 'last_name', 'email', 'password'];

    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }
}