<?php

namespace Core\Http;

use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Exceptions\HttpResponseException;
use Core\Validation\Factory as ValidatorFactory;
use Core\Validation\ValidationException;
use Core\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Coroutine;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Lớp cơ sở cho tất cả các form request.
 * Cung cấp một nơi tập trung để xử lý cả authorization và validation.
 */
abstract class FormRequest
{
    protected ServerRequestInterface $request;
    protected ?Authenticatable $user;
    protected ?Validator $validator = null;
    protected array $routeParameters = [];
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $cachedParsedBody = null;

    /**
     * Các thuộc tính không nên được flash vào session khi có lỗi validation.
     * @var array<int, string>
     */
    protected array $dontFlash = [
        'password',
        'password_confirmation',
    ];

    public function __construct(
        protected Application $app,
        protected ValidatorFactory $validatorFactory,
    ) {
        $this->request = $app->make(ServerRequestInterface::class);
        $this->user = $this->request->getAttribute('user') ?? $this->app->make(\Core\Auth\AuthManager::class)->user();

        $route = $this->request->getAttribute('route');
        $this->routeParameters = $route ? $route->parameters : [];
    }

    /**
     * Xác định xem người dùng có được phép thực hiện request này hay không.
     *
     * @return bool
     */
    abstract public function authorize(): bool;

    /**
     * Lấy các quy tắc validation sẽ được áp dụng cho request.
     *
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Lấy các quy tắc validation bất đồng bộ sẽ được áp dụng cho request.
     * Mỗi quy tắc phải là một Closure nhận ($attribute, $value) và trả về một Closure khác để thực thi trong coroutine.
     *
     * @return array
     */
    public function asyncRules(): array
    {
        return [];
    }
    
    /**
     * Lấy các thông báo lỗi tùy chỉnh cho các quy tắc validation.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Lấy các tên thuộc tính tùy chỉnh cho trình xác thực.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Chuẩn bị dữ liệu cho việc validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
    }

    /**
     * Validate request và ném exception nếu thất bại.
     * Phương thức này sẽ được gọi tự động bởi Service Container.
     */
    public function validateResolved(): void
    {
        if (!$this->authorize()) {
            throw new AccessDeniedException('This action is unauthorized.');
        }

        $this->prepareForValidation();

        // Xử lý các quy tắc bất đồng bộ trước
        $asyncValidationResults = $this->performAsyncValidation();

        $validator = $this->validatorFactory->make(
            $this->validationData(),
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );

        if (!empty($asyncValidationResults)) {
            foreach ($asyncValidationResults as $attribute => $messages) {
                $validator->addError($attribute, $messages);
            }
        }

        $this->validator = $validator;

        if ($validator->fails()) {
            $this->failedValidation($validator);
        }
    }

    /**
     * Thực thi các quy tắc validation bất đồng bộ song song.
     *
     * @return array Mảng các lỗi validation từ các quy tắc async.
     */
    protected function performAsyncValidation(): array
    {
        $asyncRules = $this->asyncRules();
        if (empty($asyncRules)) {
            return [];
        }

        $data = $this->validationData();
        $coroutines = [];
        $errors = [];

        foreach ($asyncRules as $attribute => $rules) {
            $value = $data[$attribute] ?? null;
            foreach ((array) $rules as $rule) {
                if ($rule instanceof \Closure) {
                    $coroutines[$attribute][] = function () use ($rule, $attribute, $value, &$errors) {
                        try {
                            $errorMessage = $rule($attribute, $value);
                            if (is_string($errorMessage)) {
                                $errors[$attribute][] = $errorMessage;
                            }
                        } catch (\Throwable $e) {
                            $errors[$attribute][] = "Async validation rule for '{$attribute}' failed: " . $e->getMessage();
                        }
                    };
                }
            }
        }

        Coroutine\parallel(array_merge(...array_values($coroutines)));

        return $errors;
    }
    /**
     * Lấy dữ liệu đã được validate, đã loại bỏ các tham số từ route để tăng bảo mật.
     *
     * @return array
     */
    public function validated(): array
    {
        if (empty($this->validatedData)) {
            $this->validateResolved();
        }

        if (!$this->validator) {
            throw new \LogicException('Validator not initialized before calling validated().');
        }

        return array_diff_key($this->validator->validated(), $this->routeParameters);
    }

    /**
     * Lấy dữ liệu sẽ được sử dụng cho việc validation.
     *
     * @return array
     */
    protected function validationData(): array
    {
        return array_merge(
            $this->routeParameters,
            $this->request->getQueryParams(),
            $this->getParsedBody(),
            $this->request->getUploadedFiles(),
        );
    }

    /**
     * Lấy parsed body từ request, cache lại và xử lý JSON nếu cần.
     *
     * @return array<string, mixed>
     */
    public function getParsedBody(): array
    {
        if (!is_null($this->cachedParsedBody)) {
            return $this->cachedParsedBody;
        }

        $parsedBody = $this->request->getParsedBody() ?? [];

        if (empty($parsedBody) && str_contains($this->request->getHeaderLine('Content-Type'), 'application/json')) {
            $body = $this->request->getBody()->getContents();
            if (!empty($body)) {
                $jsonBody = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $parsedBody = $jsonBody;
                }
            }
            // Rewind the stream in case it needs to be read again
            $this->request->getBody()->rewind();
        }

        return $this->cachedParsedBody = is_array($parsedBody) ? $parsedBody : [];
    }

    /**
     * Xử lý khi validation thất bại.
     *
     * @param \Core\Validation\Validator $validator
     * @return void
     * @throws \Core\Validation\ValidationException|\Core\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        if (str_contains($this->request->getHeaderLine('Accept'), 'application/json')) {
            throw new ValidationException($validator);
        }

        $redirector = $this->app->make(Redirector::class);
        $input = $this->validationData();
        foreach ($this->dontFlash as $key) {
            unset($input[$key]);
        }

        // Tạo redirect response với lỗi và input cũ
        $response = $redirector->back()
            ->withErrors($validator)
            ->withInput($input);

        throw new HttpResponseException($response);
    }

    /**
     * Lấy một file đã upload từ request.
     *
     * @param string $key
     * @return \Core\Http\UploadedFile|null
     */
    public function file(string $key): ?UploadedFile
    {
        $files = $this->request->getUploadedFiles();
        $file = $files[$key] ?? null;

        if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
            return new UploadedFile($file);
        }

        return null;
    }

    /**
     * Get all of the input and files for the request except for a few specified items.
     *
     * @param  array|string  $keys
     * @return array
     */
    public function except($keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $results = $this->validationData();

        foreach ($keys as $key) {
            unset($results[$key]);
        }

        return $results;
    }

    /**
     * Proxy các lời gọi phương thức không tồn tại đến đối tượng PSR-7 request gốc.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->request->{$method}(...$parameters);
    }

    /**
     * Lấy một tham số từ route.
     *
     * @param string $key Tên của tham số.
     * @param mixed|null $default Giá trị mặc định nếu không tìm thấy.
     * @return mixed
     */
    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }

    /**
     * Lấy người dùng đang thực hiện request.
     *
     * @return \Core\Contracts\Auth\Authenticatable|null
     */
    public function user(): ?Authenticatable
    {
        return $this->user;
    }
}
