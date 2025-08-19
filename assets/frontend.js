/**
 * Admin JavaScript for Echbay Mail Queue Manager
 */

function echbay_mail_queue_cron_send() {
	jQuery.ajax({
		url:
			window.location.origin +
			"/wp-content/plugins/echbay-viettelpost-woocommerce/cron-order.php",
		type: "GET",
		success: function (response) {
			if (response.success) {
				console.log("Cron job executed successfully", response);

				// Lập lịch lại cron job
				setTimeout(() => {
					echbay_mail_queue_cron_send();
				}, 60 * 1000);
			} else {
				console.log("Failed to execute cron job");
			}
		},
		error: function () {
			console.log("An error occurred");
		},
	});
}

// hẹn giờ nạp file cron
setTimeout(() => {
	echbay_mail_queue_cron_send();
}, 6000);
