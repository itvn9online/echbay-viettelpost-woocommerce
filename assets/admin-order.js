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

	// bỏ chức năng này
	if (1 > 2) {
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
	}

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
				// console.log("Print label response:", response);
				if (response.success) {
					if (typeof response.data["message"] != "undefined") {
						var code = response.data["message"];

						// console.log cho dev
						console.log(
							"Print label A5:",
							`https://dev-print.viettelpost.vn/DigitalizePrint/report.do?type=1&bill=${code}&showPostage=1`
						);

						// Nhãn A6
						console.log(
							"Print label A6:",
							`https://dev-print.viettelpost.vn/DigitalizePrint/report.do?type=2&bill=${code}&showPostage=1`
						);
						console.log(
							"Print label A6:",
							`https://dev-print.viettelpost.vn/DigitalizePrint/report.do?type=a6_1&bill=${code}&showPostage=1`
						);

						// Nhãn A7
						console.log(
							"Print label A7:",
							`https://dev-print.viettelpost.vn/DigitalizePrint/report.do?type=100&bill=${code}&showPostage=1`
						);
						console.log(
							"Print label A7:",
							`https://dev-print.viettelpost.vn/DigitalizePrint/report.do?type=1001&bill=${code}&showPostage=1`
						);

						// tạo HTML các mẫu in A5, A6, A7 và chèn vào sau nút .viettelpost-print-label cho người dùng admin
						var printHtml = '<div class="viettelpost-print-labels">';
						printHtml += "<h4>Mẫu in ViettelPost</h4>";
						printHtml += "<ul>";
						printHtml +=
							'<li><a href="https://digitalize.viettelpost.vn/DigitalizePrint/report.do?type=1&bill=' +
							code +
							'&showPostage=1" target="_blank">Nhãn A5</a></li>';
						printHtml +=
							'<li><a href="https://digitalize.viettelpost.vn/DigitalizePrint/report.do?type=2&bill=' +
							code +
							'&showPostage=1" target="_blank">Nhãn A6</a></li>';
						printHtml +=
							'<li><a href="https://digitalize.viettelpost.vn/DigitalizePrint/report.do?type=a6_1&bill=' +
							code +
							'&showPostage=1" target="_blank">Nhãn A6.1</a></li>';
						printHtml +=
							'<li><a href="https://digitalize.viettelpost.vn/DigitalizePrint/report.do?type=100&bill=' +
							code +
							'&showPostage=1" target="_blank">Nhãn A7</a></li>';
						printHtml +=
							'<li><a href="https://digitalize.viettelpost.vn/DigitalizePrint/report.do?type=1001&bill=' +
							code +
							'&showPostage=1" target="_blank">Nhãn A7.1</a></li>';
						printHtml += "</ul>";
						printHtml += "</div>";
						if ($(".viettelpost-print-labels").length > 0) {
							$(".viettelpost-print-labels").remove();
						}
						$button.after(printHtml);
					} else {
						alert("✓ " + response.data);
					}
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
