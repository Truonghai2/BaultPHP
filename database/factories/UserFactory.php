<?php

namespace Database\Factories;

use Core\Database\Factory;
use Modules\User\Infrastructure\Models\User;

class UserFactory extends Factory
{
    /**
     * Tên của model tương ứng với factory.
     *
     * @var string
     */
    protected string $model = User::class;

    /**
     * Định nghĩa trạng thái mặc định của model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => password_hash('password', PASSWORD_DEFAULT), // password
        ];
    }
}
