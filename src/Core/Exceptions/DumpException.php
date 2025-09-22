<?php

namespace Core\Exceptions;

/**
 * This exception is thrown by the `sdd()` helper function.
 * It extends HttpResponseException, allowing the App\Http\Kernel to catch it
 * and send the contained response directly to the client, effectively
 * halting the request gracefully without killing the Swoole worker.
 */
class DumpException extends HttpResponseException
{
}
