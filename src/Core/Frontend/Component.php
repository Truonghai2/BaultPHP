<?php

namespace Core\Frontend;

use Core\CQRS\Command;
use Core\CQRS\CommandBus;
use Core\Support\Facades\Auth;
use Core\Validation\ValidationException as FrameworkValidationException;
use Core\Validation\Validator;

/**
 * The base component for BaultPHP's frontend system.
 * It provides framework-specific helpers
 * to provide a seamless and integrated development experience.
 */
abstract class Component
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
    protected function user(): ?\Core\Contracts\Auth\Authenticatable
    {
        return Auth::user();
    }

    /**
     * Thêm một sự kiện vào hàng đợi để gửi đến trình duyệt.
     * JavaScript ở frontend sẽ bắt các sự kiện này và xử lý.
     */
    public function dispatch($event, ...$params): void
    {
        $this->dispatchQueue[] = ['event' => $event, 'params' => $params];
    }

    /**
     * Render the component.
     *
     * This method automatically determines the view name from the component's class
     * and passes all public properties to it, making them available as variables.
     *
     * @return string
     * @throws \Throwable
     */
    public function render(): string
    {
        $viewName = $this->getViewName();
        $data = $this->getState();

        return (string) view($viewName, $data);
    }

    /**
     * Get the conventional view name for the component.
     *
     * Converts a class name like `Http\Components\CounterComponent`
     * to a view name like `components.counter`.
     *
     * @return string
     */
    protected function getViewName(): string
    {
        $className = class_basename(static::class);
        $kebabName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
        $viewName = str_replace('-component', '', $kebabName);
        return 'components.' . $viewName;
    }

    /**
     * Lấy và xóa hàng đợi sự kiện. Được gọi bởi ComponentController.
     */
    public function getDispatchQueue(): array
    {
        return array_splice($this->dispatchQueue, 0);
    }

    /**
     * Lấy tất cả các thuộc tính public của component để tạo snapshot.
     * Phương thức này rất quan trọng để lưu lại trạng thái của component.
     *
     * @return array
     */
    public function getState(): array
    {
        $state = [];
        $reflector = new \ReflectionClass($this);
        $properties = $reflector->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            // Bỏ qua các thuộc tính tĩnh
            if ($property->isStatic()) {
                continue;
            }
            $state[$property->getName()] = $property->getValue($this);
        }

        return $state;
    }

    /**
     * Hydrate (khôi phục) state của component từ một snapshot.
     *
     * @param array $state
     */
    public function hydrateState(array $state): void
    {
        foreach ($state as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }

    /**
     * Ghi đè phương thức validate() gốc để throw ra Exception của framework.
     * Điều này giúp tách biệt hệ thống của bạn khỏi thư viện bên ngoài.
     */
    public function validate($rules = null, $messages = [], $attributes = []): array
    {
        $validator = new Validator($this->getState(), $rules, $messages);

        if ($validator->fails()) {
            throw new FrameworkValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Ghi đè phương thức validateOnly() gốc để throw ra Exception của framework.
     * Điều này cần thiết cho validation theo thời gian thực (real-time).
     */
    public function validateOnly($field, $rules = null, $messages = [], $attributes = [], $dataOverrides = []): array
    {
        $data = [$field => $this->getState()[$field] ?? null];
        $rules = [$field => $rules ?? []];

        $validator = new Validator($data, $rules, $messages);

        if ($validator->fails()) {
            throw new FrameworkValidationException($validator->errors());
        }

        return $validator->validated();
    }
}
