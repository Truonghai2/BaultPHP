<?php

namespace App\Http\Controllers;

use App\Http\ResponseFactory;
use Core\Config;
use Core\Frontend\FileUpload\TemporaryUploadedFile;
use Core\Http\UploadedFile;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface as Request;

class ComponentUploadController
{
    #[Route('/bault/upload-file', method: 'POST')]
    public function __invoke(Request $request, ResponseFactory $responseFactory, Config $config)
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['file'])) {
            return $responseFactory->json(['error' => 'No file uploaded.'], 422);
        }

        /** @var \Psr\Http\Message\UploadedFileInterface $psrUploadedFile */
        $psrUploadedFile = $uploadedFiles['file'];

        $maxSizeInKb = $config->get('uploads.max_size_kb', 10240);
        $allowedMimes = $config->get('uploads.allowed_mimes', []);

        if ($psrUploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $responseFactory->json(['errors' => ['file' => ['An error occurred during file upload.']]], 422);
        }

        if ($psrUploadedFile->getSize() > $maxSizeInKb * 1024) {
            return $responseFactory->json(['errors' => ['file' => ["The file must not be greater than {$maxSizeInKb} kilobytes."]]], 422);
        }

        if (!empty($allowedMimes) && !in_array($psrUploadedFile->getClientMediaType(), $allowedMimes)) {
            return $responseFactory->json(['errors' => ['file' => ['The file type is not allowed.']]], 422);
        }

        $file = new UploadedFile(
            $psrUploadedFile->getStream()->getMetadata('uri'),
            $psrUploadedFile->getClientFilename(),
            $psrUploadedFile->getClientMediaType(),
            $psrUploadedFile->getError(),
            true,
        );

        $tempFile = TemporaryUploadedFile::createFromUploadedFile($file);

        return $responseFactory->json(['path' => $tempFile->getPath()]);
    }
}
