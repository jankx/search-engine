# Jankx Search Engine

`jankx/search-engine` là một thành phần cốt lõi trong hệ sinh thái **Jankx Framework**, được thiết kế để giải quyết vấn đề hiệu năng tìm kiếm của WordPress trên mọi môi trường lưu trữ.

## ✨ Tính năng nổi bật

* **Driver-based Architecture:** Dễ dàng chuyển đổi giữa các công cụ tìm kiếm mà không cần thay đổi logic ứng dụng.
* **Shared Hosting Friendly:** Sử dụng **TNTSearch** làm driver mặc định (pure PHP) để mang lại tốc độ tìm kiếm Full-text mà không cần cài đặt thêm phần mềm server.
* **Enterprise Ready:** Hỗ trợ các adapter mạnh mẽ như **Typesense** và **Meilisearch** khi chạy trên VPS/Cloud.
* **WordPress Optimized:** Tự động lắng nghe các sự kiện `save_post`, `delete_post` để cập nhật chỉ mục (indexing) theo thời gian thực.
* **Fuzzy Search:** Hỗ trợ tìm kiếm mờ, tìm kiếm theo cụm từ và đánh trọng số kết quả.
* **Smart Facets (Bộ lọc thông minh):** Tự động tính toán số lượng kết quả (Term Counts) theo thời gian thực (Live Update) dựa trên ngữ cảnh tìm kiếm hiện tại.

## 🧩 Logic Tìm kiếm & Bộ lọc

Hệ thống áp dụng logic tìm kiếm nâng cao để đảm bảo trải nghiệm người dùng tự nhiên nhất:

1. **Logic trong cùng một nhóm (OR Condition):**  
   - Khi chọn nhiều giá trị trong cùng một nhóm (ví dụ: *Category A* và *Category B*), kết quả trả về sẽ bao gồm bài viết thuộc *Category A* **HOẶC** *Category B*.

2. **Logic giữa các nhóm khác nhau (AND Condition):**  
   - Khi chọn giá trị ở các nhóm khác nhau (ví dụ: *Category A* và *Tag X*), kết quả phải thỏa mãn cả hai điều kiện (thuộc *Category A* **VÀ** có *Tag X*).

3. **Shadow Search (Tính toán số lượng khả dụng):**  
   - Hệ thống chạy ngầm các truy vấn để tính toán trước số lượng bài viết cho mỗi bộ lọc, giúp người dùng biết trước có bao nhiêu kết quả nếu chọn bộ lọc đó (hiển thị dạng `(n)`).  
   - Bộ lọc nào có **0 kết quả** sẽ tự động bị vô hiệu hóa (disabled UI) để tránh người dùng thực hiện truy vấn rỗng.

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
