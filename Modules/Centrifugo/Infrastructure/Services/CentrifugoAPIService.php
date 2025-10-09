<?php

namespace Modules\Centrifugo\Infrastructure\Services;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class CentrifugoAPIService
{
    protected GuzzleClient $httpClient;
    protected string $apiUrl;
    protected string $apiKey;
    protected LoggerInterface $logger;

    public function __construct(string $apiUrl, string $apiKey, GuzzleClient $httpClient, LoggerInterface $logger)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Publish data to a channel.
     */
    public function publish(string $channel, array $data): bool
    {
        try {
            $response = $this->httpClient->post($this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'apikey ' . $this->apiKey,
                ],
                'json' => [
                    'method' => 'publish',
                    'params' => [
                        'channel' => $channel,
                        'data' => $data,
                    ],
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to publish message to Centrifugo', [
                'error' => $e->getMessage(),
                'channel' => $channel,
            ]);
        }

        return false;
    }
}
