# Example Module with Composer Dependencies

Đây là module example minh họa cách sử dụng Composer dependencies trong BaultFrame modules.

## Cài Đặt

### Option 1: Qua Web UI

1. Copy module này vào `Modules/ExampleModule`
2. Access admin panel
3. Hệ thống tự động phát hiện module mới
4. Click "Install" và chọn "Enable after install"
5. Dependencies sẽ được tự động cài đặt trong nền

### Option 2: Qua CLI

```bash
# Copy module
cp -r docs/examples/module-with-dependencies-example Modules/ExampleModule

# Sync module
php cli module:sync

# Install dependencies
php cli module:composer --install=ExampleModule

# Enable module
php cli module:manage --enable=ExampleModule
```

## Dependencies

Module này sử dụng các dependencies sau:

### Production Dependencies

- **monolog/monolog** (^3.0): Logging library
  - Sử dụng để log các hoạt động của module
- **intervention/image** (^2.7): Image manipulation library
  - Sử dụng để resize, crop, watermark images

- **guzzlehttp/guzzle** (^7.5): HTTP client
  - Sử dụng để call external APIs

### Dev Dependencies

- **phpunit/phpunit** (^10.0): Testing framework
- **mockery/mockery** (^1.5): Mocking library for tests

## Cách Sử Dụng Dependencies Trong Code

### Example 1: Sử Dụng Monolog

```php
<?php

namespace Modules\ExampleModule\Application\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ExampleService
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger('example-module');
        $this->logger->pushHandler(
            new StreamHandler(storage_path('logs/example-module.log'), Logger::DEBUG)
        );
    }

    public function doSomething(): void
    {
        $this->logger->info('Doing something...', ['context' => 'example']);

        // Your logic here

        $this->logger->info('Done!');
    }
}
```

### Example 2: Sử Dụng Intervention Image

```php
<?php

namespace Modules\ExampleModule\Application\Services;

use Intervention\Image\ImageManager;

class ImageService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(['driver' => 'gd']);
    }

    public function resizeImage(string $path, int $width, int $height): string
    {
        $img = $this->imageManager->make($path);

        $img->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $outputPath = storage_path('app/resized/' . basename($path));
        $img->save($outputPath);

        return $outputPath;
    }
}
```

### Example 3: Sử Dụng Guzzle HTTP Client

```php
<?php

namespace Modules\ExampleModule\Application\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ApiService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.example.com',
            'timeout' => 30,
        ]);
    }

    public function fetchData(): array
    {
        try {
            $response = $this->client->get('/endpoint');

            return json_decode($response->getBody()->getContents(), true);

        } catch (GuzzleException $e) {
            Log::error('API call failed: ' . $e->getMessage());
            return [];
        }
    }
}
```

## Autoloading

Module này có custom autoload configuration:

```json
{
  "autoload": {
    "psr-4": {
      "Modules\\ExampleModule\\": ""
    },
    "files": ["helpers.php"]
  }
}
```

Sau khi cài dependencies, chạy:

```bash
composer dump-autoload
```

## Kiểm Tra Dependencies

```bash
# Check dependencies status
php cli module:composer --check=ExampleModule

# Verify packages installed
composer show | grep -E "(monolog|intervention|guzzle)"
```

## Testing

```bash
# Run tests (nếu có phpunit configured)
./vendor/bin/phpunit Modules/ExampleModule/tests/

# Or via module test command
php cli test --module=ExampleModule
```

## Troubleshooting

### Issue: Dependencies không được cài

```bash
# Check queue worker
php cli queue:work

# Check logs
tail -f storage/logs/bault-*.log | grep ExampleModule

# Manually install
php cli module:composer --install=ExampleModule
```

### Issue: Class not found

```bash
# Regenerate autoload
composer dump-autoload

# Check autoload files
ls -la vendor/composer/

# Verify namespace
grep -r "namespace Modules\\\\ExampleModule" Modules/ExampleModule/
```

## Notes

- Dependencies được cài tự động khi module được install
- Module có thể dùng cả `module.json` (đơn giản) hoặc `composer.json` (advanced)
- Khi có `composer.json`, system sẽ merge vào root composer.json
- Rollback tự động nếu có lỗi trong quá trình cài đặt

## License

MIT
