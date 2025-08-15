<?php

namespace Core\Http;

use Psr\Http\Message\StreamInterface;

/**
 * Class StringStream
 * @package Core\Http
 */
class StringStream implements StreamInterface
{
    private string $content;
    private int $pointer = 0;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
        // No action needed for a string stream
    }

    public function detach()
    {
        $this->pointer = 0;
        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->content);
    }

    public function tell(): int
    {
        return $this->pointer;
    }

    public function eof(): bool
    {
        return $this->pointer >= $this->getSize();
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        if ($whence === SEEK_SET) {
            $this->pointer = $offset;
        } elseif ($whence === SEEK_CUR) {
            $this->pointer += $offset;
        } elseif ($whence === SEEK_END) {
            $this->pointer = $this->getSize() + $offset;
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        throw new \RuntimeException('Cannot write to a string stream.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read($length): string
    {
        $data = substr($this->content, $this->pointer, $length);
        $this->pointer += strlen($data);
        return $data;
    }

    public function getContents(): string
    {
        return substr($this->content, $this->pointer);
    }

    public function getMetadata($key = null)
    {
        return $key ? null : [];
    }
}
