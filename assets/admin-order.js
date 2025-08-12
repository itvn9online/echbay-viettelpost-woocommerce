jQuery(document).ready(function ($) {
	// Create ViettelPost order
	$(".viettelpost-create-order").on("click", function (e) {
		e.preventDefault();

		var $button = $(this);
		var orderId = $button.data("order-id");
		var originalText = $button.text();

		if (
			!confirm(
				"Bạn có chắc chắn muốn tạo vận đơn ViettelPost cho đơn hàng này?"
			)
		) {
			return;
		}

		$button.prop("disabled", true).text("Đang tạo...");

		$.ajax({
			url: viettelpost_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_create_order",
				order_id: orderId,
				nonce: viettelpost_ajax.nonce,
			},
			success: function (response) {
				if (response.success) {
					alert("✓ " + response.data);
					location.reload();
				} else {
					alert("✗ " + response.data);
				}
			},
			error: function () {
				alert("✗ Có lỗi xảy ra khi tạo vận đơn");
			},
			complete: function () {
				$button.prop("disabled", false).text(originalText);
			},
		});
	});

	// Track ViettelPost order
	$(".viettelpost-track-order").on("click", function (e) {
		e.preventDefault();

		var $button = $(this);
		var orderId = $button.data("order-id");
		var originalText = $button.text();

		$button.prop("disabled", true).text("Đang cập nhật...");

		$.ajax({
			url: viettelpost_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_track_order",
				order_id: orderId,
				nonce: viettelpost_ajax.nonce,
			},
			success: function (response) {
				if (response.success) {
					alert("✓ " + response.data);
					location.reload();
				} else {
					alert("✗ " + response.data);
				}
			},
			error: function () {
				alert("✗ Có lỗi xảy ra khi cập nhật tracking");
			},
			complete: function () {
				$button.prop("disabled", false).text(originalText);
			},
		});
	});

	// Print ViettelPost label
	$(".viettelpost-print-label").on("click", function (e) {
		e.preventDefault();

		var $button = $(this);
		var orderId = $button.data("order-id");
		var originalText = $button.text();

		$button.prop("disabled", true).text("Đang xử lý...");

		$.ajax({
			url: viettelpost_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_print_label",
				order_id: orderId,
				nonce: viettelpost_ajax.nonce,
			},
			success: function (response) {
				if (response.success) {
					alert("✓ " + response.data);
				} else {
					alert("✗ " + response.data);
				}
			},
			error: function () {
				alert("✗ Có lỗi xảy ra khi in nhãn");
			},
			complete: function () {
				$button.prop("disabled", false).text(originalText);
			},
		});
	});
});
