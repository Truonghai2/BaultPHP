<?php

namespace Core\Http;

use Core\Contracts\Auth\Authenticatable;
use Core\Support\Facades\Gate;
use Core\Validation\Factory as ValidatorFactory;
use Core\Validation\ValidationException;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Finder\Exception\AccessDeniedException;

/**
 * Lớp Controller cơ sở.
 * Cung cấp các phương thức tiện ích cho validation và authorization.
 */
abstract class Controller
{
    /**
     * Xác thực một action cho trước đối với một người dùng.
     *
     * Phương thức này kiểm tra xem người dùng hiện tại (đã xác thực) có quyền
     * thực hiện một hành động cụ thể hay không.
     *
     * @param  string  $ability Tên quyền hạn (ví dụ: 'post.update')
     * @param  mixed  $arguments Các đối số bổ sung được truyền cho policy (thường là model).
     * @return void
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException Nếu không được phép.
     */
    protected function authorize(string $ability, mixed $arguments = []): void
    {
        /** @var RequestInterface $request */
        $request = app(RequestInterface::class);
        /** @var Authenticatable|null $user */
        $user = $request->user();

        // Sử dụng Gate Facade để kiểm tra quyền.
        if (is_null($user) || !Gate::check($user, $ability, $arguments)) {
            throw new AccessDeniedException('This action is unauthorized.');
        }
    }

    /**
     * Xác thực một mảng dữ liệu cho trước dựa trên các quy tắc.
     *
     * @param  array  $data Dữ liệu cần xác thực.
     * @param  array  $rules Các quy tắc xác thực.
     * @param  array  $messages Các thông báo lỗi tùy chỉnh.
     * @param  array  $customAttributes Các tên thuộc tính tùy chỉnh cho thông báo lỗi.
     * @return array Dữ liệu đã được xác thực.
     *
     * @throws \Core\Validation\ValidationException Nếu validation thất bại.
     */
    protected function validate(array $data, array $rules, array $messages = [], array $customAttributes = []): array
    {
        $validator = app(ValidatorFactory::class)->make($data, $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
