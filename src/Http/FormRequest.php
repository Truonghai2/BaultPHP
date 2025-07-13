<?php

namespace Http;

use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Exceptions\ValidationException;
use App\Exceptions\AuthorizationException;
use Core\Validation\Factory as ValidationFactory;
use Core\Validation\Validator;

abstract class FormRequest
{
    protected Application $app;
    protected Request $request;
    protected ?Validator $validator = null;
    protected ?Authenticatable $user;

    /**
     * FormRequest được tạo thông qua container, cho phép inject các dependency.
     * Container sẽ tự động inject Application và Request gốc.
     */
    public function __construct(Application $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;
        $this->user = $this->app->make('auth')->user();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    abstract public function rules(): array;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the class instance.
     */
    public function validateResolved(): void
    {
        if (!$this->authorize()) {
            throw new AuthorizationException('This action is unauthorized.', 403);
        }

        $this->validator = $this->getValidatorInstance();

        if ($this->validator->fails()) {
            $this->failedValidation($this->validator);
        }
    }

    /**
     * Get the validated data from the request.
     */
    public function validated(): array
    {
        if (is_null($this->validator)) {
            $this->validateResolved();
        }
        if (!$this->validator) {
            throw new \LogicException('Validator not initialized before calling validated().');
        }
        return $this->validator->validated();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator);
    }

    protected function getValidatorInstance(): Validator
    {
        if ($this->validator) {
            return $this->validator;
        }

        $factory = $this->app->make(ValidationFactory::class);

        // Sử dụng factory tùy chỉnh của chúng ta
        return $factory->make(
            $this->request->all(), $this->rules(), $this->messages()
        );
    }

    public function messages(): array { return []; }

    /**
     * Proxy các lời gọi phương thức không tồn tại tới đối tượng Request gốc.
     * Điều này cho phép bạn gọi $formRequest->input('name') trong controller.
     */
    public function __call(string $method, array $args)
    {
        return $this->request->$method(...$args);
    }

    /**
     * Proxy việc truy cập thuộc tính tới đối tượng Request gốc.
     */
    public function __get(string $key)
    {
        return $this->request->$key;
    }
}