# EchBay ViettelPost WooCommerce - HPOS Compatibility Updates

## Tóm tắt thay đổi để tương thích với WooCommerce HPOS (High-Performance Order Storage)

### 1. Khai báo tương thích HPOS trong file chính

**File:** `echbay-viettelpost-woocommerce.php`

Đã thêm khai báo tương thích HPOS:

```php
// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
```

### 2. Thay thế các hàm meta không tương thích

**File:** `includes/class-viettelpost-order-handler.php`

#### Các thay đổi chính:

1. **Thay thế `get_post_meta()` bằng `$order->get_meta()`:**

   - `get_post_meta($order_id, '_viettelpost_order_number', true)`
   - → `$order->get_meta('_viettelpost_order_number', true)`

2. **Thay thế `update_post_meta()` bằng `$order->update_meta_data()` + `$order->save()`:**

   - `update_post_meta($order_id, '_viettelpost_order_number', $value)`
   - → `$order->update_meta_data('_viettelpost_order_number', $value); $order->save();`

3. **Cập nhật meta box để tương thích:**

   ```php
   public function add_order_meta_boxes()
   {
       $screen = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
           ? wc_get_page_screen_id( 'shop-order' )
           : 'shop_order';

       add_meta_box(
           'viettelpost-order-info',
           __('Thông tin ViettelPost', 'echbay-viettelpost-woocommerce'),
           array($this, 'order_meta_box_content'),
           $screen,
           'side',
           'default'
       );
   }
   ```

4. **Cập nhật callback meta box:**

   ```php
   public function order_meta_box_content($post_or_order_object)
   {
       $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;

       if (!$order) {
           return;
       }

       $order_number = $order->get_meta('_viettelpost_order_number', true);
       // ... rest of the code
   }
   ```

### 3. Các hàm được cập nhật

1. **`maybe_create_viettelpost_order()`:** Sử dụng `$order->get_meta()` thay vì `get_post_meta()`
2. **`create_viettelpost_order()`:** Sử dụng `$order->update_meta_data()` và `$order->save()`
3. **`print_viettelpost_label_action()`:** Sử dụng `$order->get_meta()`
4. **`order_meta_box_content()`:** Hỗ trợ cả post object và order object
5. **`display_viettelpost_info()`:** Sử dụng `$order->get_meta()`
6. **`display_tracking_info_frontend()`:** Sử dụng `$order->get_meta()`
7. **`update_tracking_info()`:** Sử dụng `$order->update_meta_data()` và `$order->save()`
8. **`ajax_print_label()`:** Thêm validation order và sử dụng `$order->get_meta()`

### 4. Lợi ích của việc tương thích HPOS

1. **Hiệu suất cao hơn:** HPOS lưu trữ orders trong bảng tùy chỉnh thay vì wp_posts
2. **Scalability tốt hơn:** Xử lý được nhiều orders hơn
3. **Tương thích ngược:** Plugin vẫn hoạt động với cả legacy và HPOS modes
4. **Future-proof:** Chuẩn bị cho các phiên bản WooCommerce tương lai

### 5. Kiểm tra tương thích

Plugin bây giờ đã được khai báo tương thích với HPOS và sẽ hoạt động bình thường với:

- WooCommerce Legacy Order Storage (truyền thống)
- WooCommerce High-Performance Order Storage (HPOS)

### 6. Ghi chú quan trọng

- Tất cả các thao tác order meta đã được cập nhật để sử dụng WooCommerce Order API
- Meta box được cập nhật để hỗ trợ cả hai chế độ storage
- Các AJAX handlers đã được cập nhật để làm việc với order objects
- Plugin sẽ tự động phát hiện và hoạt động với chế độ storage hiện tại

### 7. Test checklist

Để đảm bảo plugin hoạt động tốt với HPOS:

- ✅ Tạo vận đơn ViettelPost tự động
- ✅ Tạo vận đơn ViettelPost thủ công từ admin
- ✅ Hiển thị thông tin ViettelPost trong order details
- ✅ Cập nhật tracking info
- ✅ In nhãn vận đơn
- ✅ Hiển thị tracking info frontend
- ✅ Meta box ViettelPost trong order admin

Tất cả các tính năng này giờ đây đã tương thích với cả legacy storage và HPOS.
