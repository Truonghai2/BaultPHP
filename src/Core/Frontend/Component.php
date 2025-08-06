<?php

namespace Core\Frontend;

use Core\CQRS\Command;
use Core\CQRS\CommandBus;
use Core\Support\Facades\Auth;
use Core\Validation\ValidationException as FrameworkValidationException;
use Illuminate\Validation\ValidationException as IlluminateValidationException;
use Livewire\Component as BaseLivewireComponent;

/**
 * The base component for BaultPHP's frontend system.
 * It extends Livewire's core component and adds framework-specific helpers
 * to provide a seamless and integrated development experience.
 */
abstract class Component extends BaseLivewireComponent
{
    /**
     * Hàng đợi các sự kiện sẽ được gửi đến frontend.
     */
    protected array $dispatchQueue = [];

    /**
     * A helper to dispatch commands directly from a component,
     * abstracting away the need to inject the CommandBus everywhere.
     */
    protected function dispatchCommand(Command $command): mixed
    {
        return app(CommandBus::class)->dispatch($command);
    }

    /**
     * A helper to get the currently authenticated user.
     */
    protected function user(): ?\Modules\User\Infrastructure\Models\User
    {
        return Auth::user();
    }

    /**
     * Thêm một sự kiện vào hàng đợi để gửi đến trình duyệt.
     * JavaScript ở frontend sẽ bắt các sự kiện này và xử lý.
     */
    protected function dispatch(string $event, ...$params): void
    {
        $this->dispatchQueue[] = ['event' => $event, 'params' => $params];
    }

    /**
     * Lấy và xóa hàng đợi sự kiện. Được gọi bởi ComponentController.
     */
    public function getDispatchQueue(): array
    {
        return array_splice($this->dispatchQueue, 0);
    }

    /**
     * Ghi đè phương thức validate() gốc để throw ra Exception của framework.
     * Điều này giúp tách biệt hệ thống của bạn khỏi thư viện bên ngoài.
     */
    public function validate($rules = null, $messages = [], $attributes = []): array
    {
        try {
            // Gọi phương thức validate() của Livewire/Laravel
            return parent::validate($rules, $messages, $attributes);
        } catch (IlluminateValidationException $e) {
            // Bắt exception của Illuminate và throw lại exception của framework
            throw new FrameworkValidationException($e->errors());
        }
    }

    /**
     * Ghi đè phương thức validateOnly() gốc để throw ra Exception của framework.
     * Điều này cần thiết cho validation theo thời gian thực (real-time).
     */
    public function validateOnly($field, $rules = null, $messages = [], $attributes = []): array
    {
        try {
            // Gọi phương thức validateOnly() của Livewire/Laravel
            return parent::validateOnly($field, $rules, $messages, $attributes);
        } catch (IlluminateValidationException $e) {
            // Bắt exception của Illuminate và throw lại exception của framework
            throw new FrameworkValidationException($e->errors());
        }
    }
}
