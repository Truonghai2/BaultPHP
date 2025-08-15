<?php

namespace Core\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * Một collection chứa các model.
 *
 * Class này cung cấp một lớp vỏ (wrapper) tiện lợi và trôi chảy để làm việc với các mảng dữ liệu.
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Các item chứa trong collection.
     *
     * @var array
     */
    protected array $items = [];

    /**
     * Tạo một collection mới.
     *
     * @param  mixed  $items
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * Lấy tất cả các item trong collection.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Lấy số lượng item trong collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Lấy một iterator cho các item.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Xác định xem một item có tồn tại ở một offset không.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists(mixed $key): bool
    {
        return isset($this->items[$key]);
    }

    /**
     * Lấy một item tại một offset cho trước.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->items[$key];
    }

    /**
     * Gán giá trị cho một item tại một offset cho trước.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Hủy một item tại một offset cho trước.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset(mixed $key): void
    {
        unset($this->items[$key]);
    }

    /**
     * Lấy collection dưới dạng một mảng thuần túy.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(function ($value) {
            return $value instanceof Model ? $value->toArray() : $value;
        }, $this->items);
    }

    /**
     * Lấy collection dưới dạng JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Chuyển đổi đối tượng thành một thứ có thể tuần tự hóa JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Lấy ra mảng các item từ một Collection hoặc một đối tượng có thể chuyển thành mảng.
     *
     * @param  mixed  $items
     * @return array
     */
    protected function getArrayableItems($items): array
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        }

        return (array) $items;
    }

    /**
     * Kiểm tra xem collection có rỗng không.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Ánh xạ collection thành một collection mới.
     *
     * @param  callable  $callback
     * @return static
     */
    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);
        return new static(array_combine($keys, $items));
    }

    /**
     * Lọc collection bằng một callback cho trước.
     *
     * Nếu không có callback nào được cung cấp, tất cả các giá trị "falsey"
     * (false, 0, '', null, []) sẽ bị loại bỏ.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function filter(callable $callback = null): static
    {
        if ($callback) {
            // Sử dụng array_filter với cờ ARRAY_FILTER_USE_BOTH để truyền cả value và key vào callback.
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        // Nếu không có callback, lọc ra các giá trị rỗng.
        return new static(array_filter($this->items));
    }

    /**
     * Lọc collection, loại bỏ các item mà callback trả về `true`.
     *
     * Đây là phương thức ngược lại với `filter`.
     *
     * @param  callable  $callback
     * @return static
     */
    public function reject(callable $callback): static
    {
        // Chúng ta chỉ cần đảo ngược kết quả của callback
        // và sử dụng lại logic của array_filter.
        return new static(array_filter(
            $this->items,
            function ($value, $key) use ($callback) {
                return !$callback($value, $key);
            },
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Lấy item đầu tiên từ collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($this->items)) {
                return $default;
            }
            return reset($this->items);
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Lặp qua các item trong collection và thực thi một callback.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Lấy một item từ collection bằng key của nó.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Nhóm các item trong collection theo một key cho trước.
     *
     * @param  callable|string  $groupBy
     * @return static
     */
    public function groupBy($groupBy): static
    {
        $results = [];

        foreach ($this->all() as $key => $value) {
            $resolvedKey = null;
            if (is_callable($groupBy)) {
                $resolvedKey = $groupBy($value, $key);
            } elseif (is_object($value) && isset($value->{$groupBy})) {
                $resolvedKey = $value->{$groupBy};
            } elseif (is_array($value) && isset($value[$groupBy])) {
                $resolvedKey = $value[$groupBy];
            }

            if ($resolvedKey !== null) {
                $results[$resolvedKey][] = $value;
            }
        }

        return new static($results);
    }

    /**
     * Kiểm tra xem một item có tồn tại trong collection bằng key không.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Nối các giá trị của một cột cho trước.
     *
     * @param  string  $column
     * @param  string|null  $glue
     * @return string
     */
    public function implode(string $column, ?string $glue = null): string
    {
        // The `all()` method returns a plain array, which doesn't have an `implode` method.
        return implode($glue ?? '', $this->pluck($column)->all());
    }

    /**
     * Kiểm tra xem collection có phải là không rỗng.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Đặt key cho collection bằng các giá trị của một cột cho trước.
     *
     * @param  callable|string  $keyBy
     * @return static
     */
    public function keyBy($keyBy): static
    {
        $results = [];
        foreach ($this->items as $item) {
            $resolvedKey = is_callable($keyBy) ? $keyBy($item) : $item->{$keyBy};
            $results[$resolvedKey] = $item;
        }
        return new static($results);
    }

    /**
     * Lấy tất cả các key của collection.
     *
     * @return static
     */
    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    /**
     * Lấy item cuối cùng từ collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function last(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($this->items) ? $default : end($this->items);
        }

        return (new static(array_reverse($this->items, true)))->first($callback, $default);
    }

    /**
     * Lấy một mảng với các giá trị của một cột cho trước.
     *
     * @param  string  $value
     * @param  string|null  $key
     * @return static
     */
    public function pluck(string $value, ?string $key = null): static
    {
        $results = [];
        foreach ($this->items as $item) {
            $itemValue = is_object($item) ? $item->{$value} : $item[$value];

            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_object($item) ? $item->{$key} : $item[$key];
                $results[$itemKey] = $itemValue;
            }
        }
        return new static($results);
    }

    /**
     * Lấy và xóa item cuối cùng khỏi collection.
     *
     * @return mixed
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Thêm một item vào đầu collection.
     *
     * @param  mixed  $value
     * @param  mixed|null  $key
     * @return $this
     */
    public function prepend($value, $key = null): static
    {
        $items = $this->items;
        if (is_null($key)) {
            array_unshift($items, $value);
        } else {
            $items = [$key => $value] + $items;
        }
        $this->items = $items;
        return $this;
    }

    /**
     * Thêm một item vào cuối collection.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function push($value): static
    {
        $this->offsetSet(null, $value);
        return $this;
    }

    /**
     * Giảm collection xuống một giá trị duy nhất.
     *
     * @param  callable  $callback
     * @param  mixed  $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Lấy và xóa item đầu tiên khỏi collection.
     *
     * @return mixed
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * Sắp xếp collection theo một key cho trước.
     *
     * @param  callable|string  $callback
     * @param  bool  $descending
     * @return static
     */
    public function sortBy($callback, bool $descending = false): static
    {
        $results = [];
        foreach ($this->items as $key => $value) {
            $results[$key] = is_callable($callback) ? $callback($value, $key) : $value->{$callback};
        }

        $descending ? arsort($results) : asort($results);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Lấy tổng của các giá trị.
     *
     * @param  string|callable|null  $callback
     * @return mixed
     */
    public function sum($callback = null)
    {
        if (is_null($callback)) {
            return array_sum($this->items);
        }

        $callback = is_string($callback) ? fn ($item) => $item->{$callback} : $callback;

        return $this->reduce(fn ($result, $item) => $result + $callback($item), 0);
    }

    /**
     * Gọi một callback với collection và trả về collection.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function tap(callable $callback): static
    {
        $callback(new static($this->items));
        return $this;
    }

    /**
     * Trả về các item duy nhất trong collection.
     *
     * @param  string|callable|null  $key
     * @return static
     */
    public function unique($key = null): static
    {
        if (is_null($key)) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $callback = is_string($key) ? fn ($item) => $item->{$key} : $key;
        $exists = [];

        return $this->reject(function ($item) use ($callback, &$exists) {
            $id = $callback($item);
            if (in_array($id, $exists, true)) {
                return true;
            }
            $exists[] = $id;
            return false;
        });
    }

    /**
     * Trả về collection với các key được reset thành số.
     *
     * @return static
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Chuyển đổi collection thành chuỗi JSON.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    public function search(callable $callback): int|false
    {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     *
     *
     * @param integer $offset
     * @param integer|null $length
     * @param array $replacement
     * @return self
     */
    public function splice(int $offset, ?int $length = null, array $replacement = []): self
    {
        $items = $this->items;

        if ($length === null) {
            $length = count($items) - $offset;
        }

        array_splice($items, $offset, $length, $replacement);

        return new static($items);
    }

}
