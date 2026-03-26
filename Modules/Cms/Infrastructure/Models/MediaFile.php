<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;
use Core\ORM\Relations\BelongsTo;
use Modules\User\Infrastructure\Models\User;

/**
 * Media File Model
 *
 * Represents uploaded media files (images, documents, etc.)
 *
 * @property int $id
 * @property int $user_id
 * @property string $filename
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size
 * @property string $path
 * @property string $url
 * @property string|null $alt_text
 * @property string|null $title
 * @property int|null $width
 * @property int|null $height
 * @property string $folder
 * @property array|null $tags
 * @property string|null $caption
 * @property string|null $description
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property-read User $user
 */
class MediaFile extends Model
{
    use Auditable;

    protected static string $table = 'media_files';

    protected array $fillable = [
        'user_id',
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'path',
        'url',
        'alt_text',
        'title',
        'width',
        'height',
        'folder',
        'tags',
        'caption',
        'description',
    ];

    protected array $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who uploaded this file
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSize(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get thumbnail URL (for images)
     */
    public function getThumbnailUrl(int $width = 150, int $height = 150): string
    {
        if (!$this->isImage()) {
            return '/assets/images/file-icon.png';
        }

        $thumbDir = config('wasm.image.thumbnail_dir', 'public/uploads/media/thumbnails');
        $thumbUrlPrefix = config('wasm.image.thumbnail_url_prefix', '/uploads/media/thumbnails');

        $extension = pathinfo($this->filename, PATHINFO_EXTENSION) ?: 'jpg';
        $thumbName = pathinfo($this->filename, PATHINFO_FILENAME) . "_{$width}x{$height}." . $extension;

        $thumbBasePath = base_path(trim($thumbDir, '/'));
        $thumbFullPath = $thumbBasePath . '/' . $thumbName;

        if (file_exists($thumbFullPath)) {
            return rtrim($thumbUrlPrefix, '/') . '/' . $thumbName;
        }

        $originalPath = base_path('public/' . ltrim($this->path, '/'));
        if (!file_exists($originalPath)) {
            return $this->url;
        }

        if (!is_dir($thumbBasePath)) {
            mkdir($thumbBasePath, 0755, true);
        }

        try {
            wasm_image()->resize($originalPath, $width, $height, [
                'output_path' => $thumbFullPath,
                'quality' => config('wasm.image.default_quality', 85),
                'format' => $extension,
                'preserve_aspect' => true,
            ]);

            return rtrim($thumbUrlPrefix, '/') . '/' . $thumbName;
        } catch (\Throwable) {
            return $this->url;
        }
    }
}
