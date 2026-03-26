# Request Deduplication

Hệ thống tránh xử lý trùng lặp request giống nhau: một request xử lý, response được cache và chia sẻ cho các request cùng signature trong TTL.

## Cấu hình

| Biến / Config | Mô tả |
|---------------|--------|
| `enabled` | Bật/tắt deduplication (mặc định `false`). |
| `mode` | `cache_only`: chỉ trả từ cache nếu hit; miss thì xử lý bình thường, không chờ. `coalesce`: một request xử lý, request cùng signature chờ (trong `max_wait`) rồi lấy từ cache. |
| `included_paths` | Nếu **không rỗng**: chỉ các path khớp prefix mới được deduplicate (opt-in). VD: `/api/reports/`, `/api/export/`. Nếu **rỗng**: mọi GET (trừ `excluded_paths`) đều được deduplicate. |
| `excluded_paths` | Path không bao giờ deduplicate (prefix match). Luôn áp dụng. Mặc định: `/`, `/ping`, `/assets`, `/api`, `/health`, `/metrics`, ... |
| `cache_ttl` | TTL cache response (giây). |
| `lock_timeout` | Thời gian giữ lock (giây). |
| `max_wait` | Chỉ dùng khi `mode = coalesce`: thời gian tối đa chờ request đầu xong (giây). |
| `lock_wait_interval` | Khoảng cách giữa các lần kiểm tra lock (ms), khi `mode = coalesce`. |
| `include_user` | Thêm user ID vào signature (request khác user → signature khác). |
| `include_headers` | Thêm header vào signature (VD: `['X-API-Key']`). |
| `cache_key_prefix` | Prefix key Redis (mặc định `dedup:`). |

## Cách dùng

1. **Opt-in theo path (khuyến nghị):** Đặt `REQUEST_DEDUP_ENABLED=true` và `REQUEST_DEDUP_INCLUDED_PATHS=/api/reports/,/api/export/`. Chỉ các route đó mới deduplicate; GET `/` và route high-throughput không bị ảnh hưởng.

2. **Mode `cache_only`:** Không chờ request khác. Cache hit → trả ngay; miss → xử lý bình thường. Phù hợp khi không muốn tăng latency.

3. **Mode `coalesce`:** Một request xử lý, request cùng signature chờ tối đa `max_wait` giây rồi lấy từ cache. Giảm tải backend nhưng có thể tăng latency.

4. **Route tắt deduplication:** Thêm middleware `no-deduplication` cho route cụ thể.

## Signature

- Method + path + query (query được sort để `?a=1&b=2` và `?b=2&a=1` cùng signature).
- Body (POST/PUT/PATCH) hash xxh3.
- User (nếu `include_user`) từ attribute `user` (getId / id), `user_id`, hoặc header `X-User-Id`.
- Header tùy chọn (`include_headers`).

## Lock

- Lock dùng token: chỉ process giữ lock mới release được (tránh release nhầm).
- Key: `lock:{signature}`. Response cache: `{cache_key_prefix}resp:{signature}`.

## Unit test

```bash
php vendor/bin/phpunit tests/Unit/Http/Deduplication/
```
