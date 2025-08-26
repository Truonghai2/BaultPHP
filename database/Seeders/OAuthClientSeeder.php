<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\User\Infrastructure\Models\OAuth\Client;

class OAuthClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // 1. Tạo một "Confidential Client" cho luồng Authorization Code
        // Đây là client cho các ứng dụng của bên thứ ba, yêu cầu xác thực bằng secret.
        Client::firstOrCreate(
            ['id' => '9c882444-76cf-4423-9216-a0a1f485b132'],
            [
                'name' => 'My Web App',
                'secret' => 'aVerySecretKeyThatYouShouldChange', // Thay đổi key này trong môi trường production
                'redirect' => 'http://localhost:3000/callback',
                'personal_access_client' => false,
                'password_client' => false,
                'revoked' => false,
            ],
        );

        // 2. Tạo một "Password Grant Client" cho các ứng dụng first-party
        // Client này được tin tưởng để xử lý trực tiếp username/password của người dùng.
        // Rất hữu ích cho ứng dụng di động hoặc SPA của chính bạn.
        Client::firstOrCreate(
            ['id' => '9c8824a7-85b4-431c-991b-3a5a101f7a2c'],
            [
                'name' => 'My Mobile App',
                // Password grant client vẫn nên là confidential và có secret.
                'secret' => 'anotherSecretKeyYouShouldChange',
                'redirect' => 'http://localhost', // Redirect URI vẫn bắt buộc nhưng không dùng trong luồng password.
                'personal_access_client' => false,
                'password_client' => true,
                'revoked' => false,
            ],
        );

        // In ra thông tin để dễ dàng sử dụng
        $this->command->info('OAuth clients created successfully.');
        $this->command->line('  <fg=yellow>Web App (Auth Code) Client ID:</> 9c882444-76cf-4423-9216-a0a1f485b132');
        $this->command->line('  <fg=yellow>Mobile App (Password) Client ID:</> 9c8824a7-85b4-431c-991b-3a5a101f7a2c');
    }
}
