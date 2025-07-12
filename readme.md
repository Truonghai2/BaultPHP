// In some ServiceProvider, e.g., App\Providers\LoggingServiceProvider.php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\AdminActivityController;
use App\Services\Logging\LoggerInterface;
use App\Services\Logging\FileLogger;
use App\Services\Logging\DatabaseLogger;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ràng buộc mặc định (nếu có)
        $this->app->bind(LoggerInterface::class, FileLogger::class);

        // --- Bắt đầu Contextual Binding ---

        // Khi OrderController cần LoggerInterface, hãy cung cấp FileLogger.
        $this->app->when(OrderController::class)
                  ->needs(LoggerInterface::class)
                  ->give(FileLogger::class);

        // Khi AdminActivityController cần LoggerInterface, hãy cung cấp DatabaseLogger.
        $this->app->when(AdminActivityController::class)
                  ->needs(LoggerInterface::class)
                  ->give(DatabaseLogger::class);
                  
        // Bạn cũng có thể sử dụng Closure để có logic phức tạp hơn.
        $this->app->when(SomeOtherService::class)
                  ->needs(LoggerInterface::class)
                  ->give(function ($app) {
                      // Logic để tạo một logger đặc biệt...
                      return new SpecialLogger(config('some.key'));
                  });
    }
}


sử dụng Contextual binding