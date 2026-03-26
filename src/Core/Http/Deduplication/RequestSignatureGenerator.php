<?php

declare(strict_types=1);

namespace Core\Http\Deduplication;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Tạo signature duy nhất cho HTTP request (dùng cho deduplication).
 * Query string được chuẩn hóa (sort) để ?a=1&b=2 và ?b=2&a=1 cùng signature.
 */
class RequestSignatureGenerator
{
    public function generate(ServerRequestInterface $request, array $options = []): string
    {
        $parts = [];

        $parts[] = $request->getMethod();

        $uri = $request->getUri();
        $path = $uri->getPath();
        $query = $uri->getQuery();
        $normalizedQuery = $this->normalizeQueryString($query);
        $parts[] = $path . ($normalizedQuery !== '' ? '?' . $normalizedQuery : '');

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            $body = (string) $request->getBody();
            if ($body !== '') {
                $parts[] = hash('xxh3', $body);
            }
        }

        if ($options['include_user'] ?? false) {
            $userId = $this->resolveUserId($request);
            if ($userId !== null && $userId !== '') {
                $parts[] = 'user:' . $userId;
            }
        }

        $includeHeaders = $options['include_headers'] ?? [];
        foreach ($includeHeaders as $header) {
            $value = $request->getHeaderLine($header);
            if ($value !== '') {
                $parts[] = $header . ':' . $value;
            }
        }

        $payload = implode('|', $parts);
        return 'req:' . hash('xxh3', $payload);
    }

    /**
     * Chuẩn hóa query string: sort theo key để cùng params → cùng signature.
     */
    protected function normalizeQueryString(string $query): string
    {
        if ($query === '') {
            return '';
        }
        $params = [];
        parse_str($query, $params);
        ksort($params, SORT_STRING);
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function resolveUserId(ServerRequestInterface $request): ?string
    {
        $user = $request->getAttribute('user');
        if ($user !== null && is_object($user)) {
            if (method_exists($user, 'getId')) {
                return (string) $user->getId();
            }
            if (isset($user->id)) {
                return (string) $user->id;
            }
        }
        $id = $request->getAttribute('user_id');
        if ($id !== null) {
            return (string) $id;
        }
        $header = $request->getHeaderLine('X-User-Id');
        return $header !== '' ? $header : null;
    }
}
