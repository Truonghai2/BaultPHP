<?php

namespace Http;

use InvalidArgumentException;
use Nyholm\Psr7\Response;

class JsonResponse extends Response
{
    /**
     * Create a new JSON response.
     *
     * @param mixed $data The data to be encoded to JSON.
     * @param int $status The HTTP status code.
     * @param array $headers Additional headers.
     * @param int $encodingOptions JSON encoding options.
     */
    public function __construct(
        $data,
        int $status = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) {
        try {
            // Sử dụng JSON_THROW_ON_ERROR để tự động ném exception khi có lỗi
            $json = json_encode($data, $encodingOptions | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Bắt exception và ném lại dưới dạng InvalidArgumentException để nhất quán
            throw new InvalidArgumentException('JSON encoding error: ' . $e->getMessage(), 0, $e);
        }

        $headers['Content-Type'] = 'application/json';

        parent::__construct($status, $headers, $json);
    }
}
