<?php

namespace Data;

// Giả lập class Model cốt lõi
abstract class Model
{
    public static function find(int $id)
    {
        return new static();
    }
}

// Giả lập một class User cụ thể
class User extends Model
{
}