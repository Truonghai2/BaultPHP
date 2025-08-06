<?php

namespace App\Http\Controllers;

use Core\Config;
use Core\Frontend\FileUpload\TemporaryUploadedFile;
use Http\ResponseFactory;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\ServerRequestInterface as Request;

class ComponentUploadController
{
    public function __invoke(Request $request, ResponseFactory $responseFactory, Config $config)
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['file'])) {
            return $responseFactory->json(['error' => 'No file uploaded.'], 422);
        }

        /** @var \Psr\Http\Message\UploadedFileInterface $psrUploadedFile */
        $psrUploadedFile = $uploadedFiles['file'];

        // BẢO MẬT: Xác thực file upload một cách chặt chẽ.
        // Lấy cấu hình từ file config/uploads.php
        $maxSizeInKb = $config->get('uploads.max_size_kb', 10240); // Mặc định 10MB
        $allowedMimes = $config->get('uploads.allowed_mimes', []);

        // 1. Kiểm tra lỗi upload cơ bản
        if ($psrUploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $responseFactory->json(['errors' => ['file' => ['An error occurred during file upload.']]], 422);
        }

        // 2. Kiểm tra kích thước file
        if ($psrUploadedFile->getSize() > $maxSizeInKb * 1024) {
            return $responseFactory->json(['errors' => ['file' => ["The file must not be greater than {$maxSizeInKb} kilobytes."]]], 422);
        }

        // 3. Kiểm tra loại MIME
        if (!empty($allowedMimes) && !in_array($psrUploadedFile->getClientMediaType(), $allowedMimes)) {
            return $responseFactory->json(['errors' => ['file' => ['The file type is not allowed.']]], 422);
        }

        // Chuyển đổi từ PSR-7 UploadedFileInterface sang Illuminate UploadedFile để dễ xử lý
        $file = new UploadedFile(
            $psrUploadedFile->getStream()->getMetadata('uri'),
            $psrUploadedFile->getClientFilename(),
            $psrUploadedFile->getClientMediaType(),
            $psrUploadedFile->getError(),
            true // test mode
        );

        $tempFile = TemporaryUploadedFile::createFromUploadedFile($file);

        return $responseFactory->json(['path' => $tempFile->getPath()]);
    }
}
