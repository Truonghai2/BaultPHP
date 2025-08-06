<?php

namespace Http;

use App\Exceptions\AuthorizationException;
use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Exceptions\ValidationException;
use Core\Validation\Factory as ValidationFactory;
use Core\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class FormRequest
{
    protected Application $app;
    protected ServerRequestInterface $request;
    protected ?Validator $validator = null;
    protected ?Authenticatable $user;

    /**
     * FormRequest được tạo thông qua container, cho phép inject các dependency.
     * Container sẽ tự động inject Application.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
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
     * Set the current request instance for the form request.
     *
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function setRequest(ServerRequestInterface $request): static
    {
        $this->request = $request;
        $this->user = $this->user();

        return $this;
    }

    /**
     * Get the user making the request.
     */
    public function user(): ?Authenticatable
    {
        return $this->request->getAttribute('user') ?? $this->app->make('auth')->user();
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

        return $factory->make(
            $this->validationData(),
            $this->rules(),
            $this->messages(),
        );
    }

    /**
     * Get the data to be validated from the request.
     * By default, it merges route parameters, query string, and parsed body.
     */
    protected function validationData(): array
    {
        $route = $this->request->getAttribute('route');
        $routeParams = $route ? $route->parameters : [];

        return array_merge(
            $routeParams,
            $this->request->getQueryParams(),
            (array) $this->request->getParsedBody()
        );
    }

    public function messages(): array
    {
        return [];
    }

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
