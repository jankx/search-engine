# Hướng dẫn chuyển đổi MySQL Database sang TNTSearch Index

TNTSearch không trực tiếp sử dụng MySQL để tìm kiếm Full-text mà nó tạo ra một tệp chỉ mục (index) riêng biệt bằng SQLite để tối ưu tốc độ. Dưới đây là cách thực hiện cho WordPress.

## 1. Cấu trúc Index

Chúng ta sẽ tạo một tệp SQLite (ví dụ: `resources.index`) chứa các cột quan trọng từ bảng `wp_posts`:
- `id`: ID của post
- `title`: Tiêu đề bài viết
- `content`: Nội dung bài viết (đã loại bỏ HTML/Shortcode)
- `post_type`: Để lọc theo loại nội dung

## 2. Script chuyển đổi (Indexing)

Bạn có thể sử dụng script PHP dưới đây để thực hiện việc indexing thủ công hoặc qua Cron job:

```php
use TeamTNT\TNTSearch\TNTSearch;

$tnt = new TNTSearch;

$tnt->loadConfig([
    'driver'    => 'mysql',
    'host'      => DB_HOST,
    'database'  => DB_NAME,
    'username'  => DB_USER,
    'password'  => DB_PASSWORD,
    'storage'   => '/path/to/storage/', // Nơi lưu trữ file .index
    'stemmer'   => \TeamTNT\TNTSearch\Stemmer\VietnameseStemmer::class // Nếu có hỗ trợ tiếng Việt
]);

$indexer = $tnt->createIndex('resources.index');
$indexer->query("SELECT id, post_title as title, post_content as content, post_type FROM wp_posts WHERE post_status = 'publish' AND post_type IN ('post', 'featured_item');");
$indexer->run();
```

## 3. Cập nhật thời gian thực (Real-time update)

Để đảm bảo dữ liệu luôn mới, chúng ta sử dụng Hook của WordPress trong package:

```php
add_action('save_post', function($post_id, $post, $update) {
    if ($post->post_status != 'publish') return;
    
    // Logic cập nhật document vào TNTSearch index
    $search = new SearchEngine('tntsearch', $config);
    $search->update([
        'id'    => $post_id,
        'title' => $post->post_title,
        /* ... các trường khác ... */
    ]);
}, 10, 3);
```

## 4. Lưu ý quan trọng
- **Phân mảnh (Fragmentation)**: Sau nhiều lần cập nhật, file SQLite có thể bị phân mảnh. Nên chạy `optimize` định kỳ.
- **Dung lượng**: Với hàng chục ngàn bài viết, file index thường chỉ nặng vài MB, rất nhẹ cho Shared Hosting.
