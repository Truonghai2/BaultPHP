import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0', // Lắng nghe trên tất cả các network interface
        hmr: {
            host: 'localhost', // Host mà trình duyệt sẽ kết nối đến để nhận HMR
        },
    },
});
