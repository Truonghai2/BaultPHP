<?php

namespace Core\Debug;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GuzzleCollector
 *
 * Thu thập dữ liệu về các HTTP request được thực hiện bởi Guzzle.
 */
class GuzzleCollector extends DataCollector implements Renderable
{
    protected array $requests = [];
    protected float $totalTime = 0;

    /**
     * Thêm một request đã hoàn thành vào collector.
     */
    public function addRequest(
        float $duration,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?RequestException $exception = null,
    ): void {
        $this->requests[] = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'duration' => $duration,
            'duration_str' => $this->formatDuration($duration),
            'status_code' => $response ? $response->getStatusCode() : 'Error',
            'reason_phrase' => $response ? $response->getReasonPhrase() : ($exception ? $exception->getMessage() : 'Unknown Error'),
            'is_error' => $exception !== null,
            'error' => $exception ? [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ] : null,
        ];
        $this->totalTime += $duration;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'count' => count($this->requests),
            'total_time' => $this->totalTime,
            'total_time_str' => $this->formatDuration($this->totalTime),
            'requests' => $this->requests,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'guzzle';
    }

    /**
     * {@inheritdoc}
     */
    public function getWidgets(): array
    {
        return [
            'guzzle' => [
                'icon' => 'cloud-upload',
                'widget' => 'PhpDebugBar.Widgets.TimelineWidget',
                'map' => 'guzzle.requests',
                'default' => '[]',
            ],
            'guzzle:badge' => [
                'map' => 'guzzle.count',
                'default' => 0,
            ],
        ];
    }
}
