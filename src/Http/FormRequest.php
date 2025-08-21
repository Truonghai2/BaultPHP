<?php

namespace Http;

use App\Exceptions\AuthorizationException;
use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Session\Session;
use Core\Exceptions\HttpResponseException;
use Core\Exceptions\ValidationException;
use Core\Http\Redirector;
use Core\Validation\Factory as ValidationFactory;
use Core\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;

abstract class FormRequest
{
    protected Application $app;
    protected ServerRequestInterface $request;
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
        return false; // Mặc định là false để buộc lập trình viên phải định nghĩa quyền.
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
        // If the request property hasn't been set by the Kernel (for whatever reason),
        // we resolve it from the container here. This makes the FormRequest more robust
        // and solves the "accessed before initialization" error when validation is
        // triggered from within the controller via `validated()`.
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

    protected function failedValidation(Validator $validator): void
    {
        $exception = new ValidationException($validator);

        // For API requests (identified by expecting JSON), we still throw the
        // standard validation exception. The global exception handler is
        // responsible for formatting this into a 422 JSON response.
        if ($this->expectsJson()) {
            throw $exception;
        }

        // For traditional web requests, we create a redirect response
        // with the validation errors and old input flashed to the session.
        // This is then wrapped in an HttpResponseException to stop execution
        // and immediately send the redirect.
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
        // A simple check based on the Accept header.
        // A more robust implementation might check for X-Requested-With header as well.
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
