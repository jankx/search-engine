# Hướng dẫn Customizing và Enhancing Search Engine

Tài liệu này hướng dẫn cách tùy biến sâu vào search engine, bao gồm việc thêm dữ liệu vào Index và tạo giao diện (UI Presets) mới.

---

## 1. Tùy biến dữ liệu Index (Indexing Customization)

Mặc định, hệ thống index `id`, `title`, `content` và các `taxonomies` được cấu hình. Để thêm các trường dữ liệu khác (ví dụ: Meta Key), bạn sử dụng các Filter sau:

### Thêm Meta Key vào Index
Sử dụng `jankx_search_indexer_select` để thêm cột vào câu lệnh SQL.

```php
add_filter('jankx_search_indexer_select', function($sql) {
    global $wpdb;
    // Thêm meta key '_akselos_resource_type' vào index
    $sql .= ", (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_akselos_resource_type' LIMIT 1) as resource_type";
    return $sql;
});
```

### Thêm Post Types hoặc Taxonomies
```php
// Thêm post type 'webinar' vào index
add_filter('jankx_search_index_post_types', function($post_types) {
    $post_types[] = 'webinar';
    return $post_types;
});

// Thêm taxonomy 'tag' vào nội dung tìm kiếm
add_filter('jankx_search_index_taxonomies', function($taxonomies) {
    $taxonomies[] = 'post_tag';
    return $taxonomies;
});
```

---

## 2. Tạo UI Preset mới (UI Customization)

Hệ thống hỗ trợ cơ chế Preset giúp bạn thay đổi hoàn toàn giao diện mà không sửa code core.

### Bước 1: Khai báo Preset trong UX Builder
```php
add_filter('jankx_search_results_layout_class', function($class, $atts) {
    if (isset($atts['preset']) && $atts['preset'] === 'my_custom_preset') {
        return 'jankx-result-preset-custom';
    }
    return $class;
}, 10, 2);
```

### Bước 2: Render HTML cho Item
Sử dụng filter `jankx_search_result_item_html` để trả về HTML tùy chỉnh.

```php
add_filter('jankx_search_result_item_html', function($html, $post, $preset, $state) {
    if ($preset !== 'my_custom_preset') {
        return $html;
    }

    ob_start();
    ?>
    <div class="my-custom-item">
        <h3><?php echo get_the_title($post); ?></h3>
        <!-- Thêm các trường dữ liệu Meta bạn đã index ở trên -->
        <span><?php echo get_post_meta($post->ID, '_resource_type', true); ?></span>
    </div>
    <?php
    return ob_get_clean();
}, 10, 4);
```

### Bước 3: Tích hợp CSS
Sử dụng các CSS variables đã được định nghĩa để đồng bộ giao diện:
```css
.jankx-result-preset-custom {
    --jankx-search-primary: #ff5500;
    --jankx-search-radius: 20px;
}
```

---

## 3. Quản lý Featured Content
Hệ thống hỗ trợ gán nhãn bài viết nổi bật qua Meta Key `_is_featured`.

- **Action Hook**: `jankx_search_render_featured`
- **Logic**: Bạn có thể sử dụng hook này để render khu vực nổi bật trước danh sách kết quả chính.

```php
add_action('jankx_search_render_featured', function($atts) {
    // Custom render logic for featured section
});
```
