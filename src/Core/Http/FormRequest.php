<?php

namespace Core\Http;

use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Validation\Factory as ValidatorFactory;
use Core\Validation\ValidationException;
use Core\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Lớp cơ sở cho tất cả các form request.
 * Cung cấp một nơi tập trung để xử lý cả authorization và validation.
 */
abstract class FormRequest
{
    protected ServerRequestInterface $psrRequest;
    protected ?Authenticatable $user;
    protected Validator $validator;
    protected array $routeParameters = [];
    protected array $validatedData = [];

    public function __construct(
        protected Application $app,
        protected ValidatorFactory $validatorFactory,
    ) {
        $this->psrRequest = $app->make(ServerRequestInterface::class);
        $this->user = $this->psrRequest->getAttribute('user');

        // Giả định rằng router đã thêm các tham số của route vào request
        // dưới dạng một attribute. 'route.params' là một tên phổ biến.
        $this->routeParameters = $this->psrRequest->getAttribute('route.params', []);
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
        // Mặc định không làm gì. Các lớp con có thể override.
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

        $this->validator = $this->validatorFactory->make(
            $this->all(),
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );

        if ($this->validator->fails()) {
            throw new ValidationException($this->validator);
        }

        $this->validatedData = $this->validator->validated();
    }

    /**
     * Lấy dữ liệu đã được validate.
     *
     * @return array
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Lấy tất cả dữ liệu từ request (body, query, files).
     *
     * @return array
     */
    public function all(): array
    {
        return array_merge(
            $this->psrRequest->getQueryParams(),
            $this->psrRequest->getParsedBody() ?? [],
            $this->psrRequest->getUploadedFiles(),
        );
    }

    /**
     * Lấy một file đã upload từ request.
     *
     * @param string $key
     * @return \Core\Http\UploadedFile|null
     */
    public function file(string $key): ?UploadedFile
    {
        $files = $this->psrRequest->getUploadedFiles();
        $file = $files[$key] ?? null;

        if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
            return new UploadedFile($file);
        }

        return null;
    }

    /**
     * Proxy các lời gọi phương thức không tồn tại đến PSR-7 request.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->psrRequest->{$method}(...$parameters);
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
