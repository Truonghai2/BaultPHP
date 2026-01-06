<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
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
            $query->where(function($q) use ($search) {
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

            // Generate filename
            $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $folder = $data['folder'] ?? '/';
            $path = 'uploads/media' . $folder . date('Y/m/');

            // Ensure directory exists
            $fullPath = base_path('public/' . $path);
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }

            // Move file
            $file->moveTo($fullPath . $filename);

            // Get image dimensions if image
            $width = null;
            $height = null;
            if (str_starts_with($file->getClientMediaType(), 'image/')) {
                try {
                    $imageSize = getimagesize($fullPath . $filename);
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
                'mime_type' => $file->getClientMediaType(),
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
                'message' => $e->getMessage()
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
                'message' => $e->getMessage()
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

