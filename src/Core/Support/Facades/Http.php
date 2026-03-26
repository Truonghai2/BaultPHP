<?php

namespace Core\Support\Facades;

use Core\Http\Client\HttpClient;
use Core\Http\Client\Response;

/**
 * HTTP Facade.
 * 
 * @method static HttpClient baseUrl(string $url)
 * @method static HttpClient timeout(int $seconds)
 * @method static HttpClient withHeader(string $name, string $value)
 * @method static HttpClient withHeaders(array $headers)
 * @method static HttpClient withToken(string $token, string $type = 'Bearer')
 * @method static HttpClient withBasicAuth(string $username, string $password)
 * @method static HttpClient retry(int $times = 3, int $delayMs = 100)
 * @method static HttpClient asJson()
 * @method static HttpClient asForm()
 * @method static Response get(string $url, array $query = [])
 * @method static Response post(string $url, array $data = [])
 * @method static Response put(string $url, array $data = [])
 * @method static Response patch(string $url, array $data = [])
 * @method static Response delete(string $url, array $data = [])
 * 
 * @see HttpClient
 */
class Http extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HttpClient::class;
    }
}
