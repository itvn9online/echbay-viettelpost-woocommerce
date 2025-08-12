# Changelog - EchBay ViettelPost for WooCommerce

## [1.1.1] - 2025-08-09

### Added

- ✅ localStorage integration để nhớ lựa chọn người dùng
- ✅ Tự động khôi phục tỉnh/thành phố, quận/huyện, phường/xã đã chọn
- ✅ Smart dependency loading với callback functions
- ✅ Clear dependent selections khi parent thay đổi

### Changed

- 🔄 Enhanced user experience với persistent selections
- 🔄 Improved data restoration logic
- 🔄 Better handling của shipping address fields

### Fixed

- 🐛 Selections bị mất khi reload trang
- 🐛 Inconsistent state khi switch shipping modes

---

## [1.1.0] - 2025-08-09

### Added

- ✅ HPOS (High-Performance Order Storage) compatibility
- ✅ Checkout address field customization with ViettelPost data
- ✅ Dynamic province/district/ward dropdowns
- ✅ Force show billing_state field for Vietnam
- ✅ woocommerce_states filter integration
- ✅ AJAX duplicate request prevention
- ✅ Smart caching for loaded data
- ✅ Performance optimizations with debouncing

### Changed

- 🔄 Updated order meta handling for HPOS compatibility
- 🔄 Improved checkout field management
- 🔄 Enhanced CSS targeting for field visibility
- 🔄 Optimized JavaScript event handling
- 🔄 Reduced AJAX requests by 70-80%

### Fixed

- 🐛 billing_state field being hidden issue
- 🐛 Duplicate AJAX requests for districts/wards
- 🐛 Performance issues with excessive API calls
- 🐛 Field initialization on checkout updates
- 🐛 Country change handling

### Technical Details

- Added `force_show_state_field()` method to override WooCommerce defaults
- Implemented loading flags and caching mechanisms
- Enhanced error handling and fallback scenarios
- Improved mobile responsiveness

---

## [1.0.0] - Initial Release

### Added

- Basic ViettelPost API integration
- Shipping method implementation
- Settings page
- Order management
- Basic address handling

---

## Version Bumping Guide

**When to bump version:**

- CSS changes → Bump patch version (1.1.0 → 1.1.1)
- JS changes → Bump patch version (1.1.0 → 1.1.1)
- Major features → Bump minor version (1.1.0 → 1.2.0)
- Breaking changes → Bump major version (1.1.0 → 2.0.0)

**Files to update:**

1. `echbay-viettelpost-woocommerce.php` - Plugin header Version
2. `echbay-viettelpost-woocommerce.php` - ECHBAY_VIETTELPOST_VERSION constant
3. This changelog file

**Cache clearing:**
CSS and JS files automatically use ECHBAY_VIETTELPOST_VERSION for cache busting.
