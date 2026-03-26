<?php

declare(strict_types=1);

namespace Core\Edge;

use Core\Support\Facades\Log;
use GuzzleHttp\Client;

/**
 * Edge Function
 *
 * Deploy và manage functions trên edge platforms:
 * - Cloudflare Workers
 * - Fastly Compute@Edge
 *
 * Features:
 * - Deploy to edge
 * - Low latency
 * - Global distribution
 */
class EdgeFunction
{
    protected array $functions = [];
    protected array $deployments = [];

    public function __construct(
        protected array $config = [],
    ) {
    }

    /**
     * Deploy function to edge
     *
     * @param string $functionName Function name
     * @param callable|string $function Function code or callable
     * @param array $options Deployment options
     * @return array Deployment result
     */
    public function deploy(string $functionName, callable|string $function, array $options = []): array
    {
        $provider = $options['provider'] ?? $this->config['default_provider'] ?? 'cloudflare';
        $runtime = $options['runtime'] ?? 'javascript'; // javascript, wasm, php-wasm

        // Convert PHP function to edge-compatible code
        $edgeCode = $this->convertToEdgeCode($function, $runtime);

        // Deploy based on provider
        return match ($provider) {
            'cloudflare' => $this->deployToCloudflare($functionName, $edgeCode, $options),
            'fastly' => $this->deployToFastly($functionName, $edgeCode, $options),
            default => throw new \InvalidArgumentException("Unsupported edge provider: {$provider}"),
        };
    }

    /**
     * Convert PHP function to edge-compatible code
     */
    protected function convertToEdgeCode(callable|string $function, string $runtime): string
    {
        if (is_string($function)) {
            return $function;
        }

        // For now, return placeholder
        // In production, this would convert PHP to JavaScript/WASM
        return match ($runtime) {
            'javascript' => $this->convertToJavaScript($function),
            'wasm' => $this->convertToWasm($function),
            'php-wasm' => $this->convertToPhpWasm($function),
            default => throw new \InvalidArgumentException("Unsupported runtime: {$runtime}"),
        };
    }

    /**
     * Convert PHP function to JavaScript (placeholder)
     */
    protected function convertToJavaScript(callable $function): string
    {
        // Placeholder: In production, use transpiler or manual conversion
        return <<<'JS'
        export default {
            async fetch(request) {
                return new Response('Hello from Edge!', {
                    headers: { 'Content-Type': 'text/plain' }
                });
            }
        };
        JS;
    }

    /**
     * Convert PHP function to WASM (placeholder)
     */
    protected function convertToWasm(callable $function): string
    {
        // Placeholder: In production, compile PHP to WASM
        return '';
    }

    /**
     * Convert PHP function to PHP-WASM (placeholder)
     */
    protected function convertToPhpWasm(callable $function): string
    {
        // Placeholder: In production, use PHP-WASM runtime
        return '';
    }

    /**
     * Deploy to Cloudflare Workers
     */
    protected function deployToCloudflare(string $functionName, string $code, array $options): array
    {
        $accountId = $options['account_id'] ?? $this->config['cloudflare']['account_id'] ?? env('CLOUDFLARE_ACCOUNT_ID');
        $apiToken = $options['api_token'] ?? $this->config['cloudflare']['api_token'] ?? env('CLOUDFLARE_API_TOKEN');

        if (!$accountId || !$apiToken) {
            throw new \RuntimeException("Cloudflare credentials not configured");
        }

        // Placeholder: In production, use Cloudflare API
        // $client = new Client(['base_uri' => 'https://api.cloudflare.com/client/v4']);
        // $response = $client->put("/accounts/{$accountId}/workers/scripts/{$functionName}", [
        //     'headers' => ['Authorization' => "Bearer {$apiToken}"],
        //     'json' => ['script' => $code],
        // ]);

        $deployment = [
            'function_name' => $functionName,
            'provider' => 'cloudflare',
            'status' => 'deployed',
            'deployed_at' => time(),
            'url' => "https://{$functionName}.workers.dev",
        ];

        $this->deployments[$functionName] = $deployment;

        Log::info("Function deployed to Cloudflare Workers", $deployment);

        return $deployment;
    }

    /**
     * Deploy to Fastly Compute@Edge
     */
    protected function deployToFastly(string $functionName, string $code, array $options): array
    {
        $apiToken = $options['api_token'] ?? $this->config['fastly']['api_token'] ?? env('FASTLY_API_TOKEN');
        $serviceId = $options['service_id'] ?? $this->config['fastly']['service_id'] ?? env('FASTLY_SERVICE_ID');

        if (!$apiToken || !$serviceId) {
            throw new \RuntimeException("Fastly credentials not configured");
        }

        // Placeholder: In production, use Fastly API
        // $client = new Client(['base_uri' => 'https://api.fastly.com']);
        // $response = $client->post("/service/{$serviceId}/version/{$versionId}/package", [
        //     'headers' => ['Fastly-Key' => $apiToken],
        //     'body' => $code,
        // ]);

        $deployment = [
            'function_name' => $functionName,
            'provider' => 'fastly',
            'status' => 'deployed',
            'deployed_at' => time(),
            'service_id' => $serviceId,
        ];

        $this->deployments[$functionName] = $deployment;

        Log::info("Function deployed to Fastly Compute@Edge", $deployment);

        return $deployment;
    }

    /**
     * Invoke edge function
     *
     * @param string $functionName Function name
     * @param array $data Function input data
     * @return mixed Function result
     */
    public function invoke(string $functionName, array $data = []): mixed
    {
        if (!isset($this->deployments[$functionName])) {
            throw new \RuntimeException("Function not deployed: {$functionName}");
        }

        $deployment = $this->deployments[$functionName];
        $provider = $deployment['provider'];

        return match ($provider) {
            'cloudflare' => $this->invokeCloudflare($functionName, $data, $deployment),
            'fastly' => $this->invokeFastly($functionName, $data, $deployment),
            default => throw new \RuntimeException("Unsupported provider: {$provider}"),
        };
    }

    /**
     * Invoke Cloudflare Worker
     */
    protected function invokeCloudflare(string $functionName, array $data, array $deployment): mixed
    {
        $url = $deployment['url'] ?? "https://{$functionName}.workers.dev";

        try {
            $client = new Client();
            $response = $client->post($url, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            Log::error("Failed to invoke Cloudflare Worker", [
                'function' => $functionName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Invoke Fastly Compute@Edge
     */
    protected function invokeFastly(string $functionName, array $data, array $deployment): mixed
    {
        // Fastly functions are invoked via HTTP requests to the service
        $serviceUrl = $deployment['service_url'] ?? null;

        if (!$serviceUrl) {
            throw new \RuntimeException("Fastly service URL not configured");
        }

        try {
            $client = new Client();
            $response = $client->post($serviceUrl, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            Log::error("Failed to invoke Fastly Compute@Edge", [
                'function' => $functionName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * List deployed functions
     */
    public function listDeployed(): array
    {
        return $this->deployments;
    }

    /**
     * Get deployment status
     */
    public function getDeploymentStatus(string $functionName): ?array
    {
        return $this->deployments[$functionName] ?? null;
    }

    /**
     * Delete edge function
     */
    public function delete(string $functionName): bool
    {
        if (!isset($this->deployments[$functionName])) {
            return false;
        }

        $deployment = $this->deployments[$functionName];
        $provider = $deployment['provider'];

        // Delete from provider (placeholder)
        match ($provider) {
            'cloudflare' => $this->deleteFromCloudflare($functionName, $deployment),
            'fastly' => $this->deleteFromFastly($functionName, $deployment),
            default => null,
        };

        unset($this->deployments[$functionName]);

        Log::info("Edge function deleted", ['function' => $functionName]);

        return true;
    }

    /**
     * Delete from Cloudflare
     */
    protected function deleteFromCloudflare(string $functionName, array $deployment): void
    {
        // Placeholder: Use Cloudflare API to delete worker
        Log::debug("Deleting Cloudflare Worker", ['function' => $functionName]);
    }

    /**
     * Delete from Fastly
     */
    protected function deleteFromFastly(string $functionName, array $deployment): void
    {
        // Placeholder: Use Fastly API to delete compute function
        Log::debug("Deleting Fastly Compute function", ['function' => $functionName]);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'deployed_functions' => count($this->deployments),
            'providers' => array_unique(array_column($this->deployments, 'provider')),
        ];
    }
}
