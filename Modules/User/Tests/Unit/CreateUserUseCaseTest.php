<?php

namespace Modules\User\Tests\Unit;

use Core\Contracts\Events\EventDispatcherInterface;
use Modules\User\Application\UseCases\CreateUser;
use Modules\User\Domain\Events\UserWasCreated;
use Modules\User\Domain\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CreateUserUseCaseTest extends TestCase
{
    public function test_can_create_a_new_user_and_dispatches_event()
    {
        // 1. Arrange (Sắp xếp)
        // Mock các dependency. Chúng ta không muốn test repository hay dispatcher thật.
        $userRepositoryMock = $this->createMock(UserRepositoryInterface::class);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        // Định nghĩa hành vi mong muốn cho repository mock.
        $userRepositoryMock->expects($this->once())
            ->method('create')
            ->willReturn($createdUserInstance = new \Modules\User\Domain\Entities\User(1, 'John Doe', 'john@example.com'));

        // Định nghĩa hành vi mong muốn cho event dispatcher mock.
        // Chúng ta kỳ vọng phương thức `dispatch` được gọi đúng 1 lần.
        // Tham số truyền vào `dispatch` phải là một instance của UserWasCreated.
        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($createdUserInstance) {
                // Kiểm tra cả kiểu event và dữ liệu bên trong event
                return $event instanceof UserWasCreated && $event->user === $createdUserInstance;
            }));

        // Dữ liệu đầu vào
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        // Khởi tạo UseCase với các dependency đã được mock
        $useCase = new CreateUser($userRepositoryMock, $eventDispatcherMock);

        // 2. Act (Hành động)
        // Thực thi phương thức cần test
        $resultUser = $useCase->handle($userData);

        // 3. Assert (Xác nhận)
        // Kiểm tra kết quả trả về có đúng như mong đợi không.
        $this->assertInstanceOf(\Modules\User\Domain\Entities\User::class, $resultUser);
        $this->assertSame($createdUserInstance, $resultUser, 'The returned user should be the same instance from the repository mock.');
    }
}
