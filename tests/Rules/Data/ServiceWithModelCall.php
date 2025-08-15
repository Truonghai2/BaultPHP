<?php

namespace Data;

require_once __DIR__ . '/stubs.php';

class SomeService
{
    public function findUser(int $id)
    {
        // Dòng này hợp lệ vì không nằm trong Controller
        $user = User::find($id);
    }
}
