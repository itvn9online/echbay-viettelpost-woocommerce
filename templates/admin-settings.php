<?php

/**
 * Admin Settings Template
 *
 * @package EchBay_ViettelPost_WooCommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="viettelpost-settings-container">
        <div class="viettelpost-settings-main">
            <?php
            // Display settings form
            $settings = new EchBay_ViettelPost_Settings();
            woocommerce_admin_fields($settings->get_settings());
            ?>
        </div>

        <div class="viettelpost-settings-sidebar">
            <div class="viettelpost-info-box">
                <h3>Thông tin Plugin</h3>
                <p><strong>Phiên bản:</strong> <?php echo ECHBAY_VIETTELPOST_VERSION; ?></p>
                <p><strong>Tác giả:</strong> EchBay</p>
                <p><strong>Website:</strong> <a href="https://echbay.com" target="_blank">echbay.com</a></p>
            </div>

            <div class="viettelpost-help-box">
                <h3>Hướng dẫn</h3>
                <ul>
                    <li>Đăng ký tài khoản ViettelPost Partner</li>
                    <li>Nhập thông tin đăng nhập vào settings</li>
                    <li>Kiểm tra kết nối API</li>
                    <li>Đồng bộ danh sách địa chỉ</li>
                    <li>Cấu hình thông tin người gửi</li>
                    <li>Thiết lập shipping method trong WooCommerce</li>
                </ul>
            </div>

            <div class="viettelpost-support-box">
                <h3>Hỗ trợ</h3>
                <p>Nếu bạn gặp vấn đề hoặc cần hỗ trợ, vui lòng liên hệ:</p>
                <p><strong>Email:</strong> support@echbay.com</p>
                <p><strong>Website:</strong> <a href="https://echbay.com/lien-he" target="_blank">echbay.com/lien-he</a></p>
            </div>

            <div class="viettelpost-donate-box">
                <h3>Đánh giá Plugin</h3>
                <p>Nếu plugin hữu ích, vui lòng để lại đánh giá 5 sao để chúng tôi phát triển thêm nhiều tính năng mới.</p>
                <a href="#" target="_blank" class="button button-primary">Đánh giá Plugin</a>
            </div>
        </div>
    </div>
</div>

<style>
    .viettelpost-settings-container {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }

    .viettelpost-settings-main {
        flex: 2;
    }

    .viettelpost-settings-sidebar {
        flex: 1;
        max-width: 300px;
    }

    .viettelpost-info-box,
    .viettelpost-help-box,
    .viettelpost-support-box,
    .viettelpost-donate-box {
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    }

    .viettelpost-info-box h3,
    .viettelpost-help-box h3,
    .viettelpost-support-box h3,
    .viettelpost-donate-box h3 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 16px;
        color: #23282d;
    }

    .viettelpost-help-box ul {
        margin: 0;
        padding-left: 20px;
    }

    .viettelpost-help-box li {
        margin-bottom: 8px;
        line-height: 1.5;
    }

    .viettelpost-support-box p,
    .viettelpost-donate-box p {
        margin-bottom: 10px;
        line-height: 1.5;
    }

    .viettelpost-donate-box .button {
        margin-top: 10px;
    }

    @media (max-width: 768px) {
        .viettelpost-settings-container {
            flex-direction: column;
        }

        .viettelpost-settings-sidebar {
            max-width: none;
        }
    }
</style>