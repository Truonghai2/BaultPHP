<?php

namespace Core\ORM;

use ArrayAccess;
use Core\Support\Collection;
use Countable;
use Psr\Http\Message\ServerRequestInterface as Request;
use IteratorAggregate;
use JsonSerializable;

/**
 * Một Paginator có khả năng nhận biết độ dài, tương tự như của Laravel.
 * Nó cung cấp tất cả dữ liệu cần thiết để xây dựng một giao diện người dùng phân trang.
 */
class Paginator implements JsonSerializable, IteratorAggregate, Countable, ArrayAccess
{
    /**
     * Collection các item cho trang hiện tại.
     */
    protected Collection $items;

    /**
     * Tổng số item trong toàn bộ tập dữ liệu.
     */
    protected int $total;

    /**
     * Số lượng item hiển thị trên mỗi trang.
     */
    protected int $perPage;

    /**
     * Số của trang hiện tại.
     */
    protected int $currentPage;

    /**
     * Các tùy chọn cho paginator (path, pageName, v.v.).
     */
    protected array $options;

    /**
     * Số của trang cuối cùng đã được tính toán.
     */
    protected int $lastPage;

    public function __construct(Collection $items, int $total, int $perPage, int $currentPage, array $options = [])
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->options = $options;
        $this->lastPage = (int) max(ceil($total / $perPage), 1);
        $this->currentPage = $this->setCurrentPage($currentPage);
    }

    protected function setCurrentPage(int $currentPage): int
    {
        $currentPage = $currentPage > 0 ? $currentPage : 1;

        // Nếu trang hiện tại lớn hơn trang cuối cùng có thể, hãy đặt nó thành trang cuối cùng.
        return $currentPage > $this->lastPage ? $this->lastPage : $currentPage;
    }

    /**
     * Lấy URL cho một số trang cho trước.
     */
    public function url(int $page): ?string
    {
        if ($page <= 0 || $page > $this->lastPage) {
            return null;
        }

        $pageName = $this->options['pageName'] ?? 'page';
        /** @var Request $request */
        $request = app(Request::class);
        $query = $request->query();
        $query[$pageName] = $page;

        return '/' . ltrim($this->path(), '/') . '?' . http_build_query($query);
    }

    /**
     * Lấy URL cho trang kế tiếp.
     */
    public function nextPageUrl(): ?string
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }
        return null;
    }

    /**
     * Lấy URL cho trang trước đó.
     */
    public function previousPageUrl(): ?string
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }
        return null;
    }

    /**
     * Xác định xem có còn item nào trong kho dữ liệu không.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage() < $this->lastPage();
    }

    /**
     * Lấy đường dẫn cơ sở cho paginator.
     */
    protected function path(): string
    {
        return $this->options['path'] ?? app(Request::class)->path();
    }

    /**
     * Lấy số thứ tự của item đầu tiên trong lát cắt.
     */
    public function firstItem(): ?int
    {
        if ($this->items->isEmpty()) {
            return null;
        }
        return ($this->currentPage() - 1) * $this->perPage() + 1;
    }

    /**
     * Lấy số thứ tự của item cuối cùng trong lát cắt.
     */
    public function lastItem(): ?int
    {
        if ($this->items->isEmpty()) {
            return null;
        }
        return $this->firstItem() + $this->items->count() - 1;
    }

    /**
     * Lấy instance dưới dạng một mảng.
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
        ];
    }

    /**
     * Chuyển đổi đối tượng thành một thứ có thể tuần tự hóa JSON.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // Getters
    public function items(): Collection
    {
        return $this->items;
    }
    public function total(): int
    {
        return $this->total;
    }
    public function perPage(): int
    {
        return $this->perPage;
    }
    public function currentPage(): int
    {
        return $this->currentPage;
    }
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    // Interface implementations
    public function getIterator(): \Traversable
    {
        return $this->items->getIterator();
    }
    public function count(): int
    {
        return $this->items->count();
    }
    public function offsetExists(mixed $key): bool
    {
        return $this->items->offsetExists($key);
    }
    public function offsetGet(mixed $key): mixed
    {
        return $this->items->offsetGet($key);
    }
    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->items->offsetSet($key, $value);
    }
    public function offsetUnset(mixed $key): void
    {
        $this->items->offsetUnset($key);
    }
}
