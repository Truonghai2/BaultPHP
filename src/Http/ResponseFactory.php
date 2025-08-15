<?php

declare(strict_types=1);

namespace Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * ResponseFactory is responsible for creating PSR-7 responses.
 * It provides methods to create JSON responses and basic HTML responses.
 */
class ResponseFactory
{
    /**
     * Create a JSON response.
     *
     * @param mixed $data The data to be included in the JSON response.
     * @param int $status The HTTP status code for the response.
     * @param array $headers Additional headers to include in the response.
     * @param int $encodingOptions JSON encoding options (default: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).
     * @return JsonResponse|ResponseInterface
     */
    public function json(
        mixed $data = [],
        int $status = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ): JsonResponse|ResponseInterface {
        if (empty($data) && $status === 200) {
            return new Response(204, $headers);
        }
        return new JsonResponse($data, $status, $headers, $encodingOptions);
    }

    /**
     * Create a basic HTML response.
     *
     * @param string $content The HTML content to be included in the response.
     * @param int $status The HTTP status code for the response (default: 200).
     * @param array $headers Additional headers to include in the response.
     * @return ResponseInterface
     */
    public function make(string $content = '', int $status = 200, array $headers = []): ResponseInterface
    {
        if ($status === 200 && $content === '') {
            $status = 204;
        }

        if (!isset($headers['Content-Type']) && !isset($headers['content-type'])) {
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
        }

        return new Response($status, $headers, $content);
    }
}
