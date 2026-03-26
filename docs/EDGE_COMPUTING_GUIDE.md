# Edge Computing Guide

## Tổng quan

Hệ thống Edge Computing đã được triển khai với:

1. **Edge Functions** - Deploy functions lên edge platforms
2. **Edge Deployer** - Code compilation và deployment automation

## 1. Edge Functions

### Cấu hình

Thêm vào `.env`:
```env
EDGE_FUNCTIONS_ENABLED=true
EDGE_DEFAULT_PROVIDER=cloudflare
EDGE_DEFAULT_RUNTIME=javascript

# Cloudflare Workers
CLOUDFLARE_ACCOUNT_ID=your_account_id
CLOUDFLARE_API_TOKEN=your_api_token
CLOUDFLARE_WORKERS_DEV=true

# Fastly Compute@Edge
FASTLY_API_TOKEN=your_api_token
FASTLY_SERVICE_ID=your_service_id
FASTLY_SERVICE_URL=https://your-service.fastly.com
```

### Features

- ✅ **Deploy to edge** - Deploy functions to edge platforms
- ✅ **Low latency** - <10ms global latency
- ✅ **Global distribution** - Deploy to multiple edge locations
- ✅ **Multiple providers** - Support Cloudflare Workers và Fastly
- ✅ **Multiple runtimes** - JavaScript, WASM, PHP-WASM

### Sử dụng

#### Deploy Function

```php
use Core\Edge\EdgeFunction;

$edgeFunction = app(EdgeFunction::class);

// Deploy PHP function to edge
$deployment = $edgeFunction->deploy('user-validator', function ($data) {
    // Validate user data
    return [
        'valid' => isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL),
        'errors' => [],
    ];
}, [
    'provider' => 'cloudflare',
    'runtime' => 'javascript',
]);

// Returns:
// [
//     'function_name' => 'user-validator',
//     'provider' => 'cloudflare',
//     'status' => 'deployed',
//     'deployed_at' => 1234567890,
//     'url' => 'https://user-validator.workers.dev',
// ]
```

#### Deploy JavaScript Code

```php
$javascriptCode = <<<'JS'
export default {
    async fetch(request) {
        const data = await request.json();
        return new Response(JSON.stringify({
            valid: data.email && data.email.includes('@'),
        }), {
            headers: { 'Content-Type': 'application/json' }
        });
    }
};
JS;

$deployment = $edgeFunction->deploy('email-validator', $javascriptCode, [
    'provider' => 'cloudflare',
]);
```

#### Invoke Edge Function

```php
// Invoke deployed function
$result = $edgeFunction->invoke('user-validator', [
    'email' => 'user@example.com',
    'name' => 'John Doe',
]);

// Returns function result
```

#### List Deployed Functions

```php
$deployed = $edgeFunction->listDeployed();

// Returns:
// [
//     'user-validator' => [...],
//     'email-validator' => [...],
// ]
```

#### Delete Function

```php
$edgeFunction->delete('user-validator');
```

## 2. Edge Deployer

### Features

- ✅ **Code compilation** - Compile PHP to edge-compatible code
- ✅ **Deployment automation** - Automate deployment process
- ✅ **Version management** - Track deployment versions
- ✅ **Rollback support** - Rollback to previous versions

### Sử dụng

#### Deploy với Version Management

```php
use Core\Edge\EdgeDeployer;

$deployer = app(EdgeDeployer::class);

// Deploy với automatic versioning
$deployment = $deployer->deploy('api-proxy', function ($request) {
    // Proxy API request
    return Http::get($request['url'])->json();
}, [
    'provider' => 'cloudflare',
    'runtime' => 'javascript',
    'version' => '1.0.0', // Optional
]);
```

#### Rollback to Previous Version

```php
// Rollback to previous version
$deployer->rollback('api-proxy');

// Rollback to specific version
$deployer->rollback('api-proxy', '1.0.0');
```

#### Get Deployment Versions

```php
$versions = $deployer->getVersions('api-proxy');

// Returns:
// [
//     ['version' => '1.0.1', 'deployed_at' => 1234567890, ...],
//     ['version' => '1.0.0', 'deployed_at' => 1234567880, ...],
// ]
```

## Examples

### Example 1: Deploy API Proxy

```php
use Core\Edge\EdgeFunction;

$edgeFunction = app(EdgeFunction::class);

// Deploy API proxy function
$deployment = $edgeFunction->deploy('api-proxy', function ($data) {
    $url = $data['url'] ?? '';
    $method = $data['method'] ?? 'GET';
    $headers = $data['headers'] ?? [];
    
    // Proxy request
    return Http::withHeaders($headers)->{strtolower($method)}($url)->json();
}, [
    'provider' => 'cloudflare',
    'runtime' => 'javascript',
]);

// Use deployed function
$result = $edgeFunction->invoke('api-proxy', [
    'url' => 'https://api.example.com/users',
    'method' => 'GET',
]);
```

### Example 2: Deploy Validation Function

```php
use Core\Edge\EdgeFunction;

$edgeFunction = app(EdgeFunction::class);

// Deploy validation function
$deployment = $edgeFunction->deploy('validate-email', function ($data) {
    $email = $data['email'] ?? '';
    return [
        'valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        'domain' => $email ? explode('@', $email)[1] ?? null : null,
    ];
}, [
    'provider' => 'fastly',
    'runtime' => 'javascript',
]);
```

### Example 3: Deploy với Version Management

```php
use Core\Edge\EdgeDeployer;

$deployer = app(EdgeDeployer::class);

// Deploy version 1.0.0
$deployer->deploy('data-processor', function ($data) {
    return processData($data);
}, ['version' => '1.0.0']);

// Deploy version 1.1.0
$deployer->deploy('data-processor', function ($data) {
    return processDataV2($data); // Improved version
}, ['version' => '1.1.0']);

// Rollback to 1.0.0 if needed
$deployer->rollback('data-processor', '1.0.0');
```

### Example 4: Deploy to Multiple Providers

```php
use Core\Edge\EdgeFunction;

$edgeFunction = app(EdgeFunction::class);

// Deploy to Cloudflare
$cloudflareDeployment = $edgeFunction->deploy('function-name', $code, [
    'provider' => 'cloudflare',
]);

// Deploy to Fastly
$fastlyDeployment = $edgeFunction->deploy('function-name', $code, [
    'provider' => 'fastly',
]);
```

## Supported Providers

### Cloudflare Workers

- **Runtime**: JavaScript, WASM
- **Global Distribution**: 200+ locations
- **Latency**: <10ms
- **Features**: DDoS protection, auto-scaling

### Fastly Compute@Edge

- **Runtime**: JavaScript, WASM
- **Global Distribution**: 100+ locations
- **Latency**: <10ms
- **Features**: VCL integration, edge dictionaries

## Best Practices

### Edge Functions

1. **Stateless Functions**: Keep functions stateless
2. **Small Functions**: Keep functions small và focused
3. **Error Handling**: Handle errors gracefully
4. **Caching**: Use edge caching when possible
5. **Monitoring**: Monitor function performance

### Deployment

1. **Version Management**: Always use versioning
2. **Testing**: Test functions before deployment
3. **Rollback Plan**: Have rollback plan ready
4. **Monitoring**: Monitor deployments
5. **Documentation**: Document function behavior

## Troubleshooting

### Deployment Issues

**Deployment fails:**
- Check provider credentials
- Verify code syntax
- Check provider limits
- Review error logs

**Function not working:**
- Check function code
- Verify runtime compatibility
- Test locally first
- Check provider logs

### Invocation Issues

**Function timeout:**
- Optimize function code
- Reduce function complexity
- Check provider limits
- Use caching

**Function errors:**
- Check error logs
- Verify input data
- Test with sample data
- Review function code

## Performance Tips

1. **Code Size**: Keep code size small
2. **Caching**: Use edge caching
3. **Optimization**: Optimize function code
4. **Monitoring**: Monitor performance metrics
5. **Scaling**: Leverage auto-scaling

## Kết luận

Edge Computing cung cấp:

- ✅ **Edge functions** với low latency
- ✅ **Global distribution** cho better performance
- ✅ **Multiple providers** (Cloudflare, Fastly)
- ✅ **Version management** cho deployments
- ✅ **Rollback support** cho safety
- ✅ **Easy integration** với existing codebase

Deploy functions to edge để achieve <10ms global latency và improve user experience.
