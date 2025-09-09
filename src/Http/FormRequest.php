<?php

namespace Http;

use App\Exceptions\AuthorizationException;
use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Exceptions\HttpResponseException;
use Core\Http\Redirector;
use Core\Validation\Factory as ValidationFactory;
use Core\Validation\ValidationException;
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
     * @var array<int, string>
     */
    protected array $routeParameters = [];
    /**
     * Các thuộc tính không nên được flash vào session khi có lỗi validation.
     * @var array<int, string>
     */
    protected array $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * FormRequest được tạo thông qua container, cho phép inject các dependency.
     * Container sẽ tự động inject Application.
     */
    public function __construct(Application $app, ServerRequestInterface $request)
    {
        $this->app = $app;
        $this->request = $request;
        $this->user = $this->user();
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

        // Cải tiến bảo mật: Loại bỏ các tham số từ route khỏi dữ liệu đã validate.
        // Điều này ngăn chặn các lỗ hổng mass assignment khi một tham số route (ví dụ: `id`)
        // vô tình bị ghi đè bởi dữ liệu đầu vào của người dùng và sau đó được sử dụng trong các thao tác như `update()`.
        return array_diff_key($this->validator->validated(), array_flip($this->routeParameters));
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
        $exception = new ValidationException($validator); // Đã sử dụng đúng namespace mới

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

        $input = $this->getParsedBody();
        foreach ($this->dontFlash as $key) {
            unset($input[$key]);
        }

        return $redirector->back()
            ->withErrors($validator->errors())
            ->withInput($input);
    }

    /**
     * Determine if the request expects a JSON response.
     *
     * @return bool
     */
    protected function expectsJson(): bool
    {
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
        $this->routeParameters = array_keys($routeParams);

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

    public function all(): array
    {
        return array_merge(
            $this->request->getQueryParams(),
            $this->getParsedBody(),
            $this->request->getUploadedFiles(),
        );
    }
}
