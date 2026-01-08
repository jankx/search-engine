# Jankx Search Engine

`jankx/search-engine` là một thành phần cốt lõi trong hệ sinh thái **Jankx Framework**, được thiết kế để giải quyết vấn đề hiệu năng tìm kiếm của WordPress trên mọi môi trường lưu trữ.

## ✨ Tính năng nổi bật

* **Driver-based Architecture:** Dễ dàng chuyển đổi giữa các công cụ tìm kiếm mà không cần thay đổi logic ứng dụng.
* **Shared Hosting Friendly:** Sử dụng **TNTSearch** làm driver mặc định (pure PHP) để mang lại tốc độ tìm kiếm Full-text mà không cần cài đặt thêm phần mềm server.
* **Enterprise Ready:** Hỗ trợ các adapter mạnh mẽ như **Typesense** và **Meilisearch** khi chạy trên VPS/Cloud.
* **WordPress Optimized:** Tự động lắng nghe các sự kiện `save_post`, `delete_post` để cập nhật chỉ mục (indexing) theo thời gian thực.
* **Fuzzy Search:** Hỗ trợ tìm kiếm mờ, tìm kiếm theo cụm từ và đánh trọng số kết quả.

## 🚀 Các Driver hỗ trợ

| Driver | Môi trường | Loại |
| --- | --- | --- |
| `tntsearch` | Shared Hosting | PHP-native (SQLite) |
| `typesense` | VPS / Docker | In-memory Search |
| `meilisearch` | VPS / Cloud | RESTful Search API |

## 📦 Cài đặt

```bash
composer require jankx/search-engine

```

## 🛠 Cách sử dụng cơ bản

```php
use Jankx\Search\EngineManager;

$search = new EngineManager([
    'driver' => 'tntsearch', // hoặc typesense/meilisearch
    'config' => [...]
]);

$results = $search->search('từ khóa tìm kiếm');

```
