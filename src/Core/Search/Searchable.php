<?php

namespace Core\Search;

use Core\ORM\Model;
use MeiliSearch\Client;

/**
 * Trait này cung cấp khả năng tự động đồng bộ Model với Meilisearch.
 */
trait Searchable
{
    /**
     * Boot the trait.
     * Tự động đăng ký các model event listener.
     */
    public static function bootSearchable()
    {
        // Sử dụng `static::` để đảm bảo nó hoạt động đúng với class sử dụng trait.

        // Khi một model được lưu (tạo mới hoặc cập nhật),
        // hãy thêm hoặc cập nhật nó trong index của Meilisearch.
        static::saved(function (Model $model) {
            $model->makeSearchable();
        });

        // Khi một model bị xóa, hãy xóa nó khỏi index.
        static::deleted(function (Model $model) {
            $model->unsearchable();
        });
    }

    /**
     * Thực hiện tìm kiếm trên index của model.
     *
     * @param  string  $query
     * @param  array  $options
     * @return array
     */
    public static function search(string $query, array $options = []): array
    {
        /** @var Client $client */
        $client = app(Client::class);
        return $client->index(static::getSearchIndexName())->search($query, $options)->getHits();
    }

    /**
     * Đưa model này vào index của Meilisearch.
     */
    public function makeSearchable(): void
    {
        // CẢI TIẾN: Đẩy việc index vào hàng đợi (queue) để không làm chậm request.
        // Điều này yêu cầu bạn phải chạy một queue worker: `php cli queue:work`
        if ($this->getKey()) {
            dispatch(new \Core\Search\Jobs\MakeSearchableJob(static::class, $this->getKey()));
        }
    }

    /**
     * Xóa model này khỏi index của Meilisearch.
     */
    public function unsearchable(): void
    {
        // CẢI TIẾN: Đẩy việc xóa khỏi index vào hàng đợi.
        if ($this->getKey()) {
            // Lấy ra tên index trước khi model có thể bị xóa hoàn toàn.
            $indexName = static::getSearchIndexName();
            dispatch(new \Core\Search\Jobs\RemoveFromSearchJob($indexName, $this->getKey()));
        }
    }

    /**
     * Lấy ra mảng dữ liệu sẽ được gửi đến Meilisearch.
     * Bạn có thể override phương thức này trong Model của mình
     * để tùy chỉnh các trường được index.
     *
     * @return array
     */
    public function toSearchableArray(): array
    {
        // Mặc định, chúng ta sẽ index toàn bộ các thuộc tính của model.
        return $this->toArray();
    }

    /**
     * Lấy ra tên của index trong Meilisearch.
     * Mặc định là tên bảng của model.
     *
     * @return string
     */
    public static function getSearchIndexName(): string
    {
        return static::$table;
    }
}
