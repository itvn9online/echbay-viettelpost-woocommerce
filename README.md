# EchBay ViettelPost for WooCommerce

Plugin tích hợp API ViettelPost với WooCommerce để tự động tạo vận đơn, tính phí vận chuyển và theo dõi đơn hàng.

## Tính năng chính

- ✅ Tích hợp API ViettelPost Partner
- ✅ Tự động tính phí vận chuyển theo địa chỉ
- ✅ Tự động tạo vận đơn khi đơn hàng được xử lý
- ✅ Theo dõi trạng thái vận chuyển
- ✅ In nhãn vận đơn
- ✅ Quản lý đơn hàng ViettelPost trong WooCommerce Admin
- ✅ Đồng bộ danh sách tỉnh/huyện/xã từ ViettelPost
- ✅ Hỗ trợ nhiều dịch vụ vận chuyển (STK, SHT, SCN)

## Yêu cầu hệ thống

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- Tài khoản ViettelPost Partner

## Cài đặt

1. Upload thư mục plugin vào `/wp-content/plugins/`
2. Kích hoạt plugin trong WordPress Admin
3. Vào WooCommerce > Settings > ViettelPost để cấu hình

## Cấu hình

### 1. Thông tin API

- Nhập username và password ViettelPost Partner
- Nhập mã khách hàng (nếu có)
- Kiểm tra kết nối API

### 2. Thông tin người gửi

- Tên người/công ty gửi hàng
- Địa chỉ chi tiết
- Số điện thoại và email
- Chọn tỉnh/huyện/xã

### 3. Cài đặt đơn hàng

- Bật/tắt tự động tạo vận đơn
- Chọn trạng thái đơn hàng để tự động tạo
- Cân nặng mặc định cho sản phẩm
- Dịch vụ vận chuyển mặc định

### 4. Shipping Method

- Vào WooCommerce > Settings > Shipping
- Thêm ViettelPost shipping method vào shipping zone
- Cấu hình tiêu đề, dịch vụ, miễn phí vận chuyển

## Sử dụng

### Tính phí vận chuyển tự động

Plugin sẽ tự động tính phí vận chuyển dựa trên:

- Địa chỉ người nhận
- Cân nặng sản phẩm
- Dịch vụ vận chuyển được chọn

### Tạo vận đơn

Có 2 cách tạo vận đơn:

1. **Tự động**: Khi đơn hàng chuyển sang trạng thái đã cấu hình
2. **Thủ công**: Từ trang chi tiết đơn hàng hoặc order actions

### Theo dõi vận chuyển

- Thông tin tracking được cập nhật tự động
- Hiển thị trong admin và frontend
- Khách hàng có thể xem lịch sử vận chuyển

### In nhãn vận đơn

- In nhãn từ trang chi tiết đơn hàng
- Hỗ trợ in nhiều nhãn cùng lúc
- Định dạng PDF chuẩn ViettelPost

## API Endpoints

Plugin sử dụng các API của ViettelPost Partner:

- `/user/login` - Đăng nhập và lấy token
- `/categories/listProvinceById` - Danh sách tỉnh/thành
- `/categories/listDistrict` - Danh sách quận/huyện
- `/categories/listWards` - Danh sách phường/xã
- `/order/getPriceAll` - Tính phí vận chuyển
- `/order/createOrder` - Tạo vận đơn
- `/order/getOrderInfoByOrderNumber` - Thông tin vận đơn
- `/order/getOrderStatusByOrderNumber` - Trạng thái vận đơn
- `/order/print` - In nhãn vận đơn

## Hooks và Filters

### Actions

```php
do_action('echbay_viettelpost_order_created', $order_id, $viettelpost_order_number);
do_action('echbay_viettelpost_tracking_updated', $order_id, $tracking_info);
```

### Filters

```php
apply_filters('echbay_viettelpost_order_data', $order_data, $order);
apply_filters('echbay_viettelpost_shipping_cost', $cost, $package);
apply_filters('echbay_viettelpost_settings', $settings);
```

## Troubleshooting

### Lỗi kết nối API

- Kiểm tra username/password
- Đảm bảo server có thể kết nối internet
- Kiểm tra firewall/security plugins

### Không tính được phí vận chuyển

- Đồng bộ lại danh sách địa chỉ
- Kiểm tra cấu hình địa chỉ người gửi
- Kiểm tra địa chỉ người nhận có đầy đủ không

### Không tạo được vận đơn

- Kiểm tra thông tin người nhận
- Đảm bảo có cân nặng sản phẩm
- Kiểm tra log lỗi trong WooCommerce

## Changelog

### 1.0.0

- Phiên bản đầu tiên
- Tích hợp đầy đủ API ViettelPost
- Hỗ trợ tính phí, tạo vận đơn, tracking

## Hỗ trợ

- Email: lienhe@echbay.com
- Website: https://echbay.com
- Documentation: https://echbay.com/docs/viettelpost-woocommerce

## License

GPL v2 or later
