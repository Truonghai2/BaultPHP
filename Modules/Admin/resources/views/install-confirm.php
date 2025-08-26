<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác Nhận Cài Đặt Module</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; padding: 2em; background-color: #f4f6f9; color: #333; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 2em; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .module { border: 1px solid #e1e4e8; padding: 1.5em; margin-bottom: 1em; border-radius: 6px; }
        .module h3 { margin-top: 0; font-size: 1.2em; }
        .requirements { font-size: 0.9em; color: #555; background-color: #f7f7f7; padding: 0.8em; border-radius: 4px; margin-top: 1em; }
        .requirements ul { padding-left: 20px; margin: 0.5em 0; }
        .actions { margin-top: 2em; text-align: right; }
        button { padding: 0.8em 1.5em; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; }
        button:hover { background-color: #218838; }
        .note { margin-top: 2rem; padding: 1rem; background-color: #fff3cd; border: 1px solid #ffeeba; border-radius: 4px; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Phát hiện Module mới</h1>
        <p>Hệ thống đã tìm thấy các module sau chưa được cài đặt. Vui lòng xem lại thông tin, các yêu cầu (nếu có) và chọn những module bạn muốn thêm vào hệ thống.</p>

        <form action="/admin/modules/install/confirm" method="POST">
            <?php if (empty($modules)): ?>
                <p>Không có module mới nào được tìm thấy.</p>
            <?php else: ?>
                <?php foreach ($modules as $module): ?>
                    <div class="module">
                        <h3>
                            <label>
                                <input type="checkbox" name="modules[]" value="<?= htmlspecialchars($module['name']) ?>" checked>
                                <strong><?= htmlspecialchars($module['name']) ?></strong> <small>(v<?= htmlspecialchars($module['version']) ?>)</small>
                            </label>
                        </h3>
                        <p><?= htmlspecialchars($module['description']) ?></p>

                        <?php if (!empty($module['requirements'])): ?>
                            <div class="requirements">
                                <strong>Yêu cầu:</strong>
                                <ul>
                                    <?php foreach ($module['requirements'] as $key => $value): ?>
                                        <li><strong><?= htmlspecialchars($key) ?></strong>: <?= htmlspecialchars($value) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="actions">
                <button type="submit">Cài đặt các Module đã chọn</button>
            </div>
        </form>
         <div class="note">
            <h4>Lưu ý quan trọng</h4>
            <p>Sau khi cài đặt, bạn cần phải chạy lệnh migration để cập nhật cơ sở dữ liệu cho các module mới. Hãy chạy lệnh sau từ terminal trong thư mục gốc của dự án:</p>
            <pre><code>php cli ddd:migrate</code></pre>
        </div>
    </div>
</body>
</html>

