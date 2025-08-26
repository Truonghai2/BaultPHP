<?php

namespace Core\Debug;

/**
 * Exception này được ném ra bởi hàm sdd() để dừng một cách an toàn
 * việc thực thi của một request duy nhất trong môi trường Swoole.
 * ExceptionHandler sẽ bắt nó và hiển thị nội dung debug.
 */
class SwooleDumpException extends \Exception
{
    public function __construct(public readonly string $htmlContent)
    {
        parent::__construct('Swoole Dump and Die');
    }
}
