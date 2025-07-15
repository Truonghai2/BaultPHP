<?php

use Core\Contracts\Http\Kernel as KernelContract;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

require __DIR__.'/vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Khởi tạo AppKernel của bạn một lần duy nhất
$kernel = new \Core\AppKernel();
$app = $kernel->getApplication();

// Bind Http\Kernel vào container
$app->singleton(KernelContract::class, function ($app) {
    return new \Http\Kernel($app, $app->make(\Core\Routing\Router::class));
});
$httpKernel = $app->make(KernelContract::class);

$worker = Worker::create();
$psrFactory = new Psr17Factory();

$psr7 = new PSR7Worker($worker, $psrFactory, $psrFactory, $psrFactory);

while ($psrRequest = $psr7->waitRequest()) {
    if (!($psrRequest instanceof ServerRequestInterface)) { // Tín hiệu shutdown từ RoadRunner
        break;
    }

    try {
        // Chuyển đổi PSR-7 Request sang Request của bạn
        $baultRequest = \Http\Request::fromPsr7($psrRequest);

        // Đưa request vào container để có thể resolve ở nơi khác
        $app->instance(\Http\Request::class, $baultRequest);

        // Xử lý request thông qua HttpKernel
        $baultResponse = $httpKernel->handle($baultRequest);

        // Chuyển đổi Response của bạn sang PSR-7 Response và gửi đi
        $psr7->respond($baultResponse->toPsr7());
    } catch (\Throwable $e) {
        // Gửi lỗi về cho RoadRunner để ghi log
        $psr7->getWorker()->error((string)$e);
    } finally {
        // QUAN TRỌNG: Tự động reset tất cả các service đã được đăng ký là "stateful".
        // Cách tiếp cận này đảm bảo rằng không có trạng thái nào bị rò rỉ giữa các request,
        // và chúng ta không cần phải sửa đổi file này mỗi khi thêm một service stateful mới.
        if (isset($baultRequest, $baultResponse)) {
            $httpKernel->terminate($baultRequest, $baultResponse);
        }

        foreach ($app->getByTag(\Core\Contracts\StatefulService::class) as $service) {
            $service->resetState();
        }
    }
}