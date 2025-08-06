<?php

declare(strict_types=1);

namespace Modules\User\Application\Handlers;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Events\EventDispatcherInterface;
use Modules\User\Application\Commands\UpdateUserProfileCommand;
use Modules\User\Domain\Events\UserProfileUpdated;
use Modules\User\Domain\Exceptions\UserNotFoundException;
use Modules\User\Infrastructure\Models\User;

/**
 * Đây là Command Handler (Use Case). Nó chứa logic nghiệp vụ để ghi dữ liệu.
 * Nó tương tác trực tiếp với Model và bắn ra các Event.
 */
class UpdateUserProfileHandler implements CommandHandler
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @param Command|UpdateUserProfileCommand $command
     * @return void
     * @throws UserNotFoundException
     */
    public function handle(Command $command): void
    {
        /** @var UpdateUserProfileCommand $command */
        /** @var User|null $user */
        $user = User::find($command->userId);

        if (!$user) {
            throw new UserNotFoundException("User with ID {$command->userId} not found.");
        }

        // Cập nhật các thuộc tính nếu chúng được cung cấp trong command
        if (!is_null($command->name)) {
            $user->name = $command->name;
        }
        if (!is_null($command->email)) {
            // Cân nhắc thêm logic kiểm tra email duy nhất ở đây nếu cần
            $user->email = $command->email;
        }

        $user->save();

        // Bắn ra event để các phần khác của hệ thống có thể phản ứng
        $this->dispatcher->dispatch(new UserProfileUpdated($user->id));
    }
}
