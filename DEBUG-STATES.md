# Test ViettelPost States Integration

## Kiểm tra woocommerce_states filter

Để test xem filter `woocommerce_states` có hoạt động không, bạn có thể:

### 1. Kiểm tra trong WordPress admin

Vào **WooCommerce → Settings → General → Address Options** và xem:

- `Vietnam` có xuất hiện trong danh sách countries không
- Khi chọn `Vietnam`, field `State/County` có hiển thị không

### 2. Kiểm tra bằng code

Thêm đoạn code này vào file `functions.php` của theme để debug:

```php
// Debug woocommerce_states
add_action('wp_footer', function() {
    if (is_checkout()) {
        $states = WC()->countries->get_states('VN');
        echo '<script>console.log("VN States:", ' . json_encode($states) . ');</script>';
    }
});
```

### 3. Kiểm tra trong checkout

- Mở trang checkout
- Chọn country = Vietnam
- Kiểm tra `billing_state` field có hiển thị không
- Mở Developer Tools → Console để xem VN states data

### 4. Debug bằng WP_Query

```php
// Debug trong admin
add_action('admin_notices', function() {
    if (current_user_can('administrator')) {
        $states = WC()->countries->get_states('VN');
        echo '<div class="notice notice-info"><p>VN States count: ' . count($states) . '</p></div>';
    }
});
```

## Troubleshooting

### Nếu states không load:

1. **Clear cache**: Xóa tất cả cache
2. **Check data**: Verify ViettelPost provinces data đã sync
3. **Check priority**: Đảm bảo filter được add sớm
4. **Check country code**: Verify country code là 'VN'

### Nếu field vẫn ẩn:

1. **Check country locale**: WooCommerce có thể force hide
2. **Check theme**: Theme có thể override
3. **Check JavaScript**: JS có thể hide field
4. **Check CSS**: CSS có thể ẩn field

## Expected Results

Sau khi implement, bạn sẽ thấy:

✅ Vietnam có states trong WooCommerce
✅ billing_state field hiển thị cho VN
✅ Dropdown có options từ ViettelPost
✅ JavaScript có thể detect và handle

## Debug Commands

```javascript
// Trong browser console
console.log("Country:", jQuery("#billing_country").val());
console.log("State field:", jQuery("#billing_state").length);
console.log("State visible:", jQuery("#billing_state").is(":visible"));
console.log("States options:", jQuery("#billing_state option").length);
```

```php
// Trong WordPress
echo '<pre>';
var_dump(WC()->countries->get_states('VN'));
echo '</pre>';
```
