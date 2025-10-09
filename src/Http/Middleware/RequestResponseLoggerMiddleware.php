<?php

namespace App\Http\Middleware;

use Core\Config;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class RequestResponseLoggerMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    protected array $keysToSanitize;

    protected bool $logEnabled;
    protected bool $logBody;
    protected int $truncateLimit;

    public function __construct(private LoggerInterface $logger, Config $config)
    {
        $this->keysToSanitize = array_map('strtolower', $config->get('sanitizer.keys', []));

        $this->logEnabled = (bool) $config->get('logging.access.enabled', false);
        $this->logBody = (bool) $config->get('logging.access.log_body', false);
        $this->truncateLimit = (int) $config->get('logging.access.truncate_limit', 1000);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->logEnabled) {
            return $handler->handle($request);
        }

        $startTime = microtime(true);

        $response = $handler->handle($request);

        $duration = round((microtime(true) - $startTime) * 1000);

        $context = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            'request_headers' => $request->getHeaders(),
            'response_headers' => $response->getHeaders(),
        ];

        if ($this->logBody) {
            $context['request_body'] = $this->getSanitizedRequestBody($request);
            $context['response_body'] = $this->getSanitizedResponseBody($response);
        }

        $this->logger->info(
            sprintf('Request Handled: %s %s', $request->getMethod(), $request->getUri()->getPath()),
            $context,
        );

        return $response;
    }

    private function getSanitizedRequestBody(ServerRequestInterface $request): string
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            return $this->truncate(json_encode($this->sanitize($parsedBody)));
        }

        $stream = $request->getBody();
        if ($stream->isReadable()) {
            $stream->rewind();
            $bodyContent = $stream->getContents();
            $stream->rewind();
            return $this->truncate($this->sanitizeBodyString($bodyContent));
        }

        return '';
    }

    private function getSanitizedResponseBody(ResponseInterface $response): string
    {
        $body = $response->getBody();
        $body->rewind();
        $content = $body->getContents();
        $body->rewind();

        return $this->truncate($this->sanitizeBodyString($content));
    }

    /**
     * Cắt bớt nội dung body quá dài để tránh làm đầy file log.
     */
    private function truncate(string $string, ?int $limit = null): string
    {
        if (mb_strlen($string) > $limit) {
            return mb_substr($string, 0, $limit) . '... [truncated]';
        }
        return $string;
    }

    /**
     * Sanitize an array of data recursively.
     */
    private function sanitize(array $data): array
    {
        if (empty($this->keysToSanitize)) {
            return $data;
        }

        foreach ($data as $key => &$value) {
            if (in_array(strtolower((string) $key), $this->keysToSanitize, true)) {
                $value = '********';
            } elseif (is_array($value)) {
                $value = $this->sanitize($value);
            }
        }

        return $data;
    }

    /**
     * Sanitize a JSON string.
     */
    private function sanitizeBodyString(string $body): string
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $sanitizedData = $this->sanitize($data);
            return json_encode($sanitizedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            // If it's not a valid JSON string, we can't sanitize it based on keys.
            // Return the original (truncated) body.
            return $body;
        }
    }
}
