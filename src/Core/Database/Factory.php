<?php

namespace Core\Database;

use Faker\Generator as Faker;
use LogicException;

/**
 * Class Factory
 *
 * Lớp cơ sở cho tất cả các model factory. Cung cấp một cách thuận tiện
 * để tạo dữ liệu giả cho các model phục vụ cho việc testing và seeding.
 *
 * @package Core\Database
 */
abstract class Factory
{
    /**
     * Số lượng model cần tạo.
     * @var int
     */
    protected int $count = 1;

    /**
     * Tên class của model tương ứng.
     * @var string
     */
    protected string $model;

    /**
     * Các trạng thái (state) sẽ được áp dụng cho model.
     * @var array
     */
    protected array $states = [];

    /**
     * Instance của Faker.
     * @var \Faker\Generator
     */
    protected Faker $faker;

    /**
     * Tạo một factory instance mới.
     */
    public function __construct()
    {
        if (empty($this->model)) {
            throw new LogicException('Thuộc tính $model phải được định nghĩa trong factory.');
        }
        // Sử dụng locale từ config, nếu không có thì mặc định là en_US
        $this->faker = \Faker\Factory::create(config('app.faker_locale', 'en_US'));
    }

    /**
     * Định nghĩa trạng thái mặc định của model.
     *
     * @return array<string, mixed>
     */
    abstract public function definition(): array;

    /**
     * Thiết lập số lượng model cần tạo.
     */
    public function count(int $count): static
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Thêm một trạng thái (state) để áp dụng khi tạo model.
     * State có thể là một mảng các thuộc tính hoặc một closure.
     */
    public function state(array|callable $state): static
    {
        $this->states[] = $state;
        return $this;
    }

    /**
     * Tạo model và lưu vào cơ sở dữ liệu.
     *
     * @param array $attributes Các thuộc tính ghi đè.
     * @return mixed Trả về một model instance nếu count = 1, hoặc một mảng các instance nếu count > 1.
     */
    public function create(array $attributes = []): mixed
    {
        if ($this->count === 1 && empty($this->states)) {
            return $this->createOne($attributes);
        }

        $instances = [];
        for ($i = 0; $i < $this->count; $i++) {
            $instances[] = $this->createOne($attributes);
        }

        return $instances;
    }

    /**
     * Tạo một model instance nhưng không lưu vào cơ sở dữ liệu.
     *
     * @param array $attributes Các thuộc tính ghi đè.
     * @return mixed Trả về một model instance nếu count = 1, hoặc một mảng các instance nếu count > 1.
     */
    public function make(array $attributes = []): mixed
    {
        if ($this->count === 1 && empty($this->states)) {
            return $this->makeOne($attributes);
        }

        $instances = [];
        for ($i = 0; $i < $this->count; $i++) {
            $instances[] = $this->makeOne($attributes);
        }

        return $instances;
    }

    /**
     * Tạo và lưu một model instance duy nhất.
     */
    protected function createOne(array $attributes = []): mixed
    {
        $instance = $this->makeOne($attributes);
        $instance->save();
        return $instance;
    }

    /**
     * Tạo một model instance duy nhất mà không lưu.
     */
    protected function makeOne(array $attributes = []): mixed
    {
        $attrs = $this->getAttributes($attributes);
        return new $this->model($attrs);
    }

    /**
     * Lấy ra các thuộc tính cuối cùng sau khi đã hợp nhất definition, states và attributes ghi đè.
     */
    protected function getAttributes(array $overrides): array
    {
        $attributes = $this->definition();

        foreach ($this->states as $state) {
            $stateAttributes = is_callable($state) ? $state($attributes, $this->faker) : $state;
            $attributes = array_merge($attributes, $stateAttributes);
        }

        return array_merge($attributes, $overrides);
    }
}