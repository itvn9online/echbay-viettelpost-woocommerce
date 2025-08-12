# ViettelPost WooCommerce - Hệ thống Checkout Tùy chỉnh

## Tổng quan

Plugin ViettelPost WooCommerce đã được cập nhật với hệ thống checkout tùy chỉnh cho phép khách hàng chọn địa chỉ từ dữ liệu ViettelPost một cách linh hoạt và chính xác.

## Tính năng chính

### 1. Dropdown động cho địa chỉ

- **Tỉnh/Thành phố**: Hiển thị danh sách tỉnh thành từ ViettelPost
- **Quận/Huyện**: Tự động load khi chọn tỉnh/thành phố
- **Phường/Xã**: Tự động load khi chọn quận/huyện

### 2. Tương thích HPOS

- Hoàn toàn tương thích với High-Performance Order Storage
- Sử dụng order object methods thay vì post meta
- Lưu trữ dữ liệu địa chỉ an toàn và hiệu quả

### 3. Validation và UX

- Kiểm tra bắt buộc phải chọn phường/xã
- Loading states khi tải dữ liệu
- Styling responsive cho mobile
- Thông báo lỗi rõ ràng

## Cấu hình

### 1. Kích hoạt tính năng

Vào **WooCommerce → Cài đặt → ViettelPost** và bật:

- ✅ **Kích hoạt tùy chỉnh checkout**: Cho phép sử dụng dropdown địa chỉ ViettelPost

### 2. Đồng bộ dữ liệu

Đảm bảo dữ liệu ViettelPost đã được đồng bộ:

- Tỉnh/thành phố
- Quận/huyện
- Phường/xã

## Cách hoạt động

### 1. Frontend Checkout

```
Khách hàng chọn Tỉnh/Thành phố
↓ (AJAX request)
Hệ thống load danh sách Quận/Huyện
↓ (Khách hàng chọn)
Hệ thống load danh sách Phường/Xã
↓ (Khách hàng chọn)
Validation và lưu đơn hàng
```

### 2. Backend Admin

- Hiển thị thông tin địa chỉ chi tiết trong trang quản lý đơn hàng
- Lưu trữ cả ID và tên của phường/xã
- Tương thích với export và báo cáo

## Files được tạo/cập nhật

### 1. Class chính

- **includes/class-viettelpost-checkout.php**: Xử lý logic checkout
- **assets/checkout.js**: JavaScript cho frontend
- **assets/checkout.css**: Styling cho checkout form

### 2. Files cập nhật

- **echbay-viettelpost-woocommerce.php**: Thêm class mới
- **includes/class-viettelpost-order-handler.php**: HPOS compatibility
- **includes/class-viettelpost-settings.php**: Thêm setting mới

## API Endpoints

### 1. Lấy danh sách quận/huyện

```
POST /wp-admin/admin-ajax.php
action: viettelpost_get_checkout_districts
province_id: [ID tỉnh]
nonce: [security nonce]
```

### 2. Lấy danh sách phường/xã

```
POST /wp-admin/admin-ajax.php
action: viettelpost_get_checkout_wards
district_id: [ID quận]
nonce: [security nonce]
```

## Tùy chỉnh CSS

Bạn có thể tùy chỉnh giao diện bằng cách override CSS:

```css
/* Tùy chỉnh dropdown */
.woocommerce form .form-row select[data-field-type] {
	border-radius: 8px;
	border-color: #your-color;
}

/* Loading state */
.viettelpost-loading::after {
	border-top-color: #your-brand-color;
}
```

## Hooks và Filters

### 1. Actions

- `viettelpost_checkout_before_field_override`: Trước khi override fields
- `viettelpost_checkout_after_save`: Sau khi lưu checkout data

### 2. Filters

- `viettelpost_checkout_provinces`: Tùy chỉnh danh sách tỉnh
- `viettelpost_checkout_field_priority`: Thay đổi thứ tự field

## Troubleshooting

### 1. Dropdown không load

- Kiểm tra AJAX URL trong browser console
- Đảm bảo nonce security đúng
- Verify dữ liệu ViettelPost đã sync

### 2. Validation lỗi

- Kiểm tra field names trong checkout
- Đảm bảo JavaScript đã load đúng
- Verify HPOS compatibility

### 3. Styling bị lỗi

- Clear cache
- Kiểm tra CSS conflicts với theme
- Verify responsive breakpoints

## Performance

### 1. Caching

- Dữ liệu dropdown được cache
- AJAX responses được optimize
- Minimal DOM manipulation

### 2. Mobile optimization

- Touch-friendly dropdowns
- Prevent zoom on iOS
- Optimized for slow connections

## Bảo mật

### 1. AJAX Security

- Nonce verification cho mọi request
- Sanitization input data
- Permission checks

### 2. Data Validation

- Server-side validation
- XSS protection
- SQL injection prevention

## Hỗ trợ

Nếu gặp vấn đề, vui lòng kiểm tra:

1. WordPress version compatibility
2. WooCommerce version compatibility
3. PHP error logs
4. Browser console errors
5. Plugin conflicts
