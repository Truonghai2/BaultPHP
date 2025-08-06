<?php

namespace Core\Frontend\Concerns;

use Core\Frontend\FileUpload\TemporaryUploadedFile;

/**
 * Trait này cho phép một component xử lý việc upload file.
 */
trait WithFileUploads
{
    /**
     * Chuyển đổi một đường dẫn file tạm thời thành một đối tượng TemporaryUploadedFile.
     *
     * @param mixed $value
     * @return mixed|TemporaryUploadedFile
     */
    public function __get($property)
    {
        $value = $this->{$property};

        if (TemporaryUploadedFile::isTemporaryFile($value)) {
            return TemporaryUploadedFile::createFromTemporaryPath($value);
        }

        return $value;
    }
}
