import fs from 'fs';
import path from 'path';
import { visualizer } from 'rollup-plugin-visualizer';
import { fileURLToPath } from 'url';
import { defineConfig, loadEnv } from 'vite';
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';

// Trong ES Modules, __dirname không có sẵn theo mặc định.
// Chúng ta cần phải tự định nghĩa nó từ import.meta.url.
const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Plugin tùy chỉnh để tự động tạo file `public/hot` khi chạy dev server.
 * File này giúp BaultPHP biết cách kết nối tới Vite dev server.
 */
const baultHotReload = {
    name: 'bault-php-hot-reload',
    configureServer(server) {
        const hotFile = path.join(__dirname, 'public/hot');

        server.httpServer.on('listening', () => {
            // Lấy URL local mà server đang lắng nghe, đáng tin cậy hơn là hardcode.
            // Ví dụ: http://127.0.0.1:5173
            const devServerUrl = server.resolvedUrls?.local[0] || `http://localhost:${server.config.server.port}`;
            fs.writeFileSync(hotFile, devServerUrl);
        });
        // Tự động xóa file 'hot' khi server tắt
        server.httpServer.on('close', () => {
            const hotFile = path.join(__dirname, 'public/hot');
            if (fs.existsSync(hotFile)) {
                fs.unlinkSync(hotFile);
            }
        });
    }
};

export default defineConfig(({ mode }) => {
    // Load các biến môi trường từ file .env tương ứng với mode hiện tại.
    // Tiền tố thứ 3 là '' để load tất cả các biến, không chỉ các biến có tiền tố VITE_.
    const env = loadEnv(mode, process.cwd(), '');

    const plugins = [
        baultHotReload,
        ViteImageOptimizer({
            // Các tùy chọn mặc định đã khá tốt, bạn có thể tùy chỉnh thêm ở đây
            // Ví dụ:
            // png: { quality: 86 },
            // jpeg: { quality: 80 },
        }),
    ];

    // Chỉ thêm visualizer vào khi build và có biến môi trường ANALYZE=true.
    // Biến môi trường load từ .env luôn là string.
    if (env.ANALYZE === 'true') {
        plugins.push(
            visualizer({
                open: true,
                filename: 'public/build/stats.html',
                gzipSize: true,
                brotliSize: true,
            })
        );
    }

    return {
        plugins,
        build: {
            // Cấu hình thư mục output và manifest cho BaultPHP
            outDir: 'public/build',
            manifest: true,
            rollupOptions: {
                input: 'resources/js/app.js',
            },
        },
        server: {
            host: '0.0.0.0', // Lắng nghe trên tất cả các network interface, hữu ích cho Docker
            port: 5173,
        }
    };
});
