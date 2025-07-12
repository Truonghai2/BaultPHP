<?php

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

require __DIR__.'/vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Khởi tạo AppKernel của bạn một lần duy nhất
$appKernel = new \Core\AppKernel();

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

        // Xử lý request thông qua AppKernel
        $baultResponse = $appKernel->handle($baultRequest);

        // Chuyển đổi Response của bạn sang PSR-7 Response và gửi đi
        $psr7->respond($baultResponse->toPsr7());
    } catch (\Throwable $e) {
        // Gửi lỗi về cho RoadRunner để ghi log
        $psr7->getWorker()->error((string)$e);
    } finally {
        // QUAN TRỌNG: Reset các service có trạng thái sau mỗi request.
        // Điều này ngăn chặn dữ liệu từ request này rò rỉ sang request tiếp theo.
        if (class_exists(\Core\Support\Facades\Auth::class)) {
            \Core\Support\Facades\Auth::reset();
        }
        // Bạn có thể thêm các lệnh reset cho các service có trạng thái khác ở đây.
    }
}