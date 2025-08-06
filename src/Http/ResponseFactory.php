<?php

declare(strict_types=1);

namespace Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Factory để tạo các loại response khác nhau một cách nhất quán.
 */
class ResponseFactory
{
    /**
     * Tạo một JsonResponse mới.
     */
    public function json(
        mixed $data = [],
        int $status = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ): JsonResponse|ResponseInterface {
        if (empty($data) && $status === 200) {
            return new Response(204, $headers);
        }
        return new JsonResponse($data, $status, $headers, $encodingOptions);
    }

    /**
     * Tạo một ResponseInterface cơ bản.
     */
    public function make(string $content = '', int $status = 200, array $headers = []): ResponseInterface
    {
        if ($status === 200 && $content === '') {
            $status = 204;
        }

        // Ensure the Content-Type header is set for HTML responses.
        // This prevents character encoding issues in the browser.
        if (!isset($headers['Content-Type']) && !isset($headers['content-type'])) {
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
        }

        return new Response($status, $headers, $content);
    }
}
