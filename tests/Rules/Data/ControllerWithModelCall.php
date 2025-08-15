<?php

namespace Data;

require_once __DIR__ . '/stubs.php';

class SomeController
{
    public function show(int $id)
    {
        // Dòng này phải bị quy tắc bắt lỗi
        $user = User::find($id);
    }
}
