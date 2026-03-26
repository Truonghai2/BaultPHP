<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Security\FileValidator;
use Core\Support\Facades\Auth;
use Modules\Cms\Infrastructure\Models\MediaFile;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Media Library Controller
 *
 * Manage media files (images, documents, etc.)
 */
#[Route(prefix: '/admin/media', middleware: ['auth'], group: 'web')]
class MediaLibraryController extends Controller
{
    /**
     * Show media library
     * GET /admin/media
     */
    #[Route('', method: 'GET', name: 'admin.media.index')]
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        if (!config('app.debug') && !$user->can('cms.media.view')) {
            return response('Forbidden', 403);
        }

        // Get query parameters
        $queryParams = $request->getQueryParams();
        $folder = $queryParams['folder'] ?? '/';
        $search = $queryParams['search'] ?? '';
        $type = $queryParams['type'] ?? 'all'; // all, image, document

        // Build query
        $query = MediaFile::query();

        if ($folder !== 'all') {
            $query->where('folder', $folder);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                  ->orWhere('original_filename', 'like', "%{$search}%")
                  ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }

        if ($type !== 'all') {
            $query->where('mime_type', 'like', "{$type}/%");
        }

        // Paginate
        $perPage = 50;
        $page = (int)($queryParams['page'] ?? 1);
        $total = $query->count();

        $media = $query->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        // Get folders
        $folders = MediaFile::select('folder')
            ->distinct()
            ->pluck('folder')
            ->toArray();

        return response(view('admin.media.index', [
            'media' => $media,
            'folders' => $folders,
            'currentFolder' => $folder,
            'search' => $search,
            'type' => $type,
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $page,
        ]));
    }

    /**
     * Upload media file
     * POST /admin/media/upload
     */
    #[Route('/upload', method: 'POST', name: 'admin.media.upload')]
    public function upload(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.media.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            $uploadedFiles = $request->getUploadedFiles();

            if (!isset($uploadedFiles['file'])) {
                return response()->json(['error' => 'No file uploaded'], 400);
            }

            $file = $uploadedFiles['file'];
            $data = $request->getParsedBody();

            // Validate file
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            if (!in_array($file->getClientMediaType(), $allowedTypes)) {
                return response()->json(['error' => 'Invalid file type'], 400);
            }

            // Validate and sanitize folder path to prevent path traversal
            $folder = $data['folder'] ?? '/';
            
            // Remove path traversal attempts
            $folder = str_replace(['..', '\\'], '', $folder);
            $folder = trim($folder, '/');
            // Allow only safe characters: alphanumeric, dash, underscore, slash
            $folder = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $folder);
            
            // Validate file extension
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx'];
            $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'error' => 'File extension not allowed',
                    'allowed' => implode(', ', $allowedExtensions)
                ], 400);
            }
            
            // Verify MIME type matches extension
            $allowedMimeMap = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];
            
            $clientMimeType = $file->getClientMediaType();
            if (isset($allowedMimeMap[$extension]) && $clientMimeType !== $allowedMimeMap[$extension]) {
                return response()->json([
                    'error' => 'File MIME type mismatch',
                    'expected' => $allowedMimeMap[$extension],
                    'actual' => $clientMimeType
                ], 400);
            }
            
            // Generate secure filename (use random bytes instead of predictable uniqid)
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            
            // Build safe path
            $basePath = base_path('public/uploads/media/');
            $relativePath = ($folder ? $folder . '/' : '') . date('Y/m/');
            $fullPath = $basePath . $relativePath;
            $path = '/uploads/media/' . $relativePath;
            
            // Validate path is within base directory (prevent path traversal)
            $realBase = realpath($basePath);
            if (!file_exists($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            $realPath = realpath($fullPath);
            
            if (!$realPath || !str_starts_with($realPath, $realBase)) {
                return response()->json(['error' => 'Invalid upload path'], 400);
            }

            // Move file to temporary location first
            $uploadedPath = $fullPath . $filename;
            $file->moveTo($uploadedPath);

            // SECURITY: Validate file after upload (polyglot/MIME check)
            $expectedMime = $allowedMimeMap[$extension] ?? $clientMimeType;
            $validation = FileValidator::validate($uploadedPath, $expectedMime, [
                'max_size' => 10485760, // 10MB
            ]);

            if (!$validation['valid']) {
                @unlink($uploadedPath);
                return response()->json([
                    'error' => 'File validation failed',
                    'details' => $validation['errors'],
                ], 400);
            }

            // SECURITY: For images, sanitize to strip malicious content
            $width = null;
            $height = null;
            if (str_starts_with($validation['detected_mime'], 'image/')) {
                // Re-encode image to strip EXIF/metadata and potential payloads
                $sanitizedPath = $fullPath . 'sanitized_' . $filename;
                $sanitized = FileValidator::sanitizeImage($uploadedPath, $sanitizedPath, [
                    'quality' => 90,
                    'format' => $extension === 'png' ? 'png' : 'jpeg',
                ]);

                if ($sanitized) {
                    // Replace original with sanitized version
                    @unlink($uploadedPath);
                    rename($sanitizedPath, $uploadedPath);
                }

                // Get dimensions from sanitized image
                try {
                    $imageSize = getimagesize($uploadedPath);
                    $width = $imageSize[0] ?? null;
                    $height = $imageSize[1] ?? null;
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            // Create media record
            $media = MediaFile::create([
                'user_id' => $user->id,
                'filename' => $filename,
                'original_filename' => $file->getClientFilename(),
                'mime_type' => $validation['detected_mime'], // Use server-detected MIME
                'size' => $file->getSize(),
                'path' => $path . $filename,
                'url' => asset($path . $filename),
                'alt_text' => $data['alt_text'] ?? null,
                'title' => $data['title'] ?? pathinfo($file->getClientFilename(), PATHINFO_FILENAME),
                'width' => $width,
                'height' => $height,
                'folder' => $folder,
            ]);

            return response()->json([
                'success' => true,
                'media' => [
                    'id' => $media->id,
                    'filename' => $media->filename,
                    'original_filename' => $media->original_filename,
                    'url' => $media->url,
                    'mime_type' => $media->mime_type,
                    'size' => $media->getFormattedSize(),
                    'width' => $media->width,
                    'height' => $media->height,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update media metadata
     * PUT /admin/media/{id}
     */
    #[Route('/{id}', method: 'PUT', name: 'admin.media.update')]
    public function update(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $media = MediaFile::find($id);

        if (!$media) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        $data = $request->getParsedBody();

        // Update metadata
        if (isset($data['alt_text'])) {
            $media->alt_text = $data['alt_text'];
        }
        if (isset($data['title'])) {
            $media->title = $data['title'];
        }
        if (isset($data['caption'])) {
            $media->caption = $data['caption'];
        }
        if (isset($data['description'])) {
            $media->description = $data['description'];
        }

        $media->save();

        return response()->json([
            'success' => true,
            'media' => $media,
        ]);
    }

    /**
     * Delete media file
     * DELETE /admin/media/{id}
     */
    #[Route('/{id}', method: 'DELETE', name: 'admin.media.delete')]
    public function delete(int $id): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.media.delete')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $media = MediaFile::find($id);

        if (!$media) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        try {
            // Delete physical file
            $fullPath = base_path('public/' . $media->path);
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            // Delete database record
            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Delete failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get media by ID (for picker)
     * GET /admin/media/{id}
     */
    #[Route('/{id}', method: 'GET', name: 'admin.media.show')]
    public function show(int $id): Response
    {
        $media = MediaFile::find($id);

        if (!$media) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        return response()->json(['media' => $media]);
    }
}
