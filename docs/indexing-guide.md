# Hướng dẫn Quản lý Index với TNTSearch

Hệ thống sử dụng TNTSearch để tạo chỉ mục tìm kiếm Full-text bằng tệp SQLite, giúp tăng tốc độ tìm kiếm vượt trội so với truy vấn MySQL thông thường.

## 1. Cơ chế hoạt động
- **Initial Indexing**: Tạo file `resources.index` từ dữ liệu hiện có trong Database.
- **Real-time Synchronization**: Tự động cập nhật chỉ mục mỗi khi có bài viết được tạo mới, cập nhật hoặc xóa thông qua hook `save_post`.
- **Scheduled Rebuild**: Tự động xây dựng lại toàn bộ chỉ mục hàng ngày qua WP-Cron để đảm bảo tính toàn vẹn và tối ưu hóa file SQLite.

---

## 2. Các lệnh quản lý (WP-CLI)

Sử dụng WP-CLI để quản lý index nhanh chóng:

- **Xây dựng lại toàn bộ index**:
  ```bash
  wp jankx-search rebuild
  ```
- **Xây dựng lại file index cụ thể**:
  ```bash
  wp jankx-search rebuild --index=my_custom.index
  ```

---

## 3. Tự động hóa với WP-Cron

Mặc định, hệ thống đăng ký một sự kiện định kỳ:
- **Action**: `jankx_search_rebuild_index`
- **Schedule**: `daily` (Mỗi ngày một lần)

Bạn có thể kiểm tra trạng thái Cron bằng các plugin như WP Crontrol hoặc lệnh:
```bash
wp cron event list --hook=jankx_search_rebuild_index
```

---

## 4. Tùy biến dữ liệu Index

Để thêm các Meta Key hoặc thay đổi logic truy vấn dữ liệu vào Index, hãy tham khảo [Tài liệu Customization](./customization.md).

Ví dụ nhanh: thêm taxonomy tùy chỉnh vào content có thể tìm kiếm:
```php
add_filter('jankx_search_index_taxonomies', function($taxonomies) {
    return array_merge($taxonomies, ['brand', 'location']);
});
```

---

## 5. Cảnh báo trong Dashboard
Nếu file index chưa được khởi tạo, một thông báo sẽ xuất hiện trong Admin Dashboard. Bạn có thể nhấn nút **"Rebuild Index Now"** để thực hiện ngay lập tức mà không cần dùng dòng lệnh.

Hệ thống sẽ lưu vết lần rebuild cuối cùng qua Option: `jankx_search_last_rebuild_{index_name}`.
