<?php

namespace Core\WebSocket;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

class CentrifugoAPIService
{
    private HttpClient $httpClient;
    private string $apiKey;

    /**
     * @param string $apiUrl URL của Centrifugo API, ví dụ: http://127.0.0.1:8000
     * @param string $apiKey API key để xác thực với Centrifugo.
     */
    public function __construct(string $apiUrl, string $apiKey)
    {
        $this->httpClient = new HttpClient(['base_uri' => $apiUrl, 'timeout' => 2.0]);
        $this->apiKey = $apiKey;
    }

    /**
     * Gửi dữ liệu đến một channel cụ thể trong Centrifugo.
     *
     * @param string $channel Channel cần gửi đến (ví dụ: 'news' hoặc '#user_123').
     * @param array  $data    Dữ liệu (payload) cần gửi.
     * @return bool True nếu thành công, false nếu thất bại.
     */
    public function publish(string $channel, array $data): bool
    {
        return $this->sendCommand('publish', [
            'channel' => $channel,
            'data' => $data,
        ]);
    }

    private function sendCommand(string $method, array $params): bool
    {
        try {
            $this->httpClient->post('/api', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'apikey ' . $this->apiKey,
                ],
                'json' => ['method' => $method, 'params' => $params],
            ]);
            return true;
        } catch (GuzzleException $e) {
            // Trong ứng dụng thực tế, bạn nên log lỗi này.
            error_log("Failed to send command to Centrifugo: " . $e->getMessage());
            return false;
        }
    }
}