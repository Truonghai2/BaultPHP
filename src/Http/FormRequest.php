<?php

namespace Http;

use App\Exceptions\AuthorizationException;
use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Exceptions\HttpResponseException;
use Core\Exceptions\ValidationException;
use Core\Http\Redirector;
use Core\Validation\Factory as ValidationFactory;
use Core\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;

abstract class FormRequest
{
    protected Application $app;
    protected ?ServerRequestInterface $request = null;
    protected ?Validator $validator = null;
    protected ?Authenticatable $user;
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $cachedParsedBody = null;

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
        return false;
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
        if (!isset($this->user)) {
            $this->user = $this->app->make(\Core\Auth\AuthManager::class)->user();
        }

        return $this->user;
    }

    /**
     * Validate the class instance.
     */
    public function validateResolved(): void
    {
        if (!isset($this->request)) {
            $this->setRequest($this->app->make(ServerRequestInterface::class));
        }

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

    /**
     * Retrieve a validated uploaded file from the request.
     *
     * @param string $key The key for the uploaded file.
     * @return \Psr\Http\Message\UploadedFileInterface|null
     */
    public function file(string $key): ?\Psr\Http\Message\UploadedFileInterface
    {
        $validated = $this->validated();
        $file = $validated[$key] ?? null;

        return $file instanceof \Psr\Http\Message\UploadedFileInterface ? $file : null;
    }

    /**
     * Check if a validated uploaded file exists on the request.
     *
     * @param string $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        return null !== $this->file($key);
    }

    protected function failedValidation(Validator $validator): void
    {
        $exception = new ValidationException($validator);

        if ($this->expectsJson()) {
            throw $exception;
        }

        throw new HttpResponseException(
            $this->redirectBackWithErrors($validator),
        );
    }

    /**
     * Create a redirect response with validation errors.
     *
     * @param  \Core\Validation\Validator $validator
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function redirectBackWithErrors(Validator $validator)
    {
        /** @var Redirector $redirector */
        $redirector = $this->app->make(Redirector::class);

        return $redirector->back()
            ->withErrors($validator->errors())
            ->withInput($this->getParsedBody());
    }

    /**
     * Determine if the request expects a JSON response.
     *
     * @return bool
     */
    protected function expectsJson(): bool
    {
        if (!isset($this->request)) {
            return false;
        }
        return str_contains($this->request->getHeaderLine('Accept'), 'application/json');
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
            $this->getParsedBody(),
            $this->request->getUploadedFiles(),
        );
    }

    /**
     * Get the parsed body from the request, caching it for subsequent calls.
     * This prevents issues with consuming the request stream more than once.
     *
     * @return array<string, mixed>
     */
    public function getParsedBody(): array
    {
        if (!isset($this->request)) {
            $this->setRequest($this->app->make(ServerRequestInterface::class));
        }

        if (is_null($this->cachedParsedBody)) {
            $this->cachedParsedBody = (array) $this->request->getParsedBody();
        }

        return $this->cachedParsedBody;
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
        if (!isset($this->request)) {
            $this->setRequest($this->app->make(ServerRequestInterface::class));
        }

        return $this->request->$method(...$args);
    }

    /**
     * Proxy việc truy cập thuộc tính tới đối tượng Request gốc.
     */
    public function __get(string $key)
    {
        if (!isset($this->request)) {
            $this->setRequest($this->app->make(ServerRequestInterface::class));
        }

        return $this->request->$key;
    }

    public function all(): array
    {
        if (!isset($this->request)) {
            $this->setRequest($this->app->make(ServerRequestInterface::class));
        }

        return array_merge(
            $this->request->getQueryParams(),
            $this->getParsedBody(),
            $this->request->getUploadedFiles(),
        );
    }
}
