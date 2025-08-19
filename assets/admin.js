jQuery(document).ready(function () {
	// Auto-load districts and wards on page load if province/district are already selected
	function initializeLocationDropdowns() {
		var $provinceSelect = jQuery("#echbay_viettelpost_sender_province");
		var $districtSelect = jQuery("#echbay_viettelpost_sender_district");
		var $wardSelect = jQuery("#echbay_viettelpost_sender_ward");

		var selectedProvince = $provinceSelect.val();
		var selectedDistrict = $districtSelect.val();
		var selectedWard = $wardSelect.val();

		// Store current values
		$districtSelect.data("selected-value", selectedDistrict);
		$wardSelect.data("selected-value", selectedWard);

		if (selectedProvince && selectedProvince !== "") {
			loadDistricts(selectedProvince, selectedDistrict);
		}
	}

	// Function to load districts
	function loadDistricts(provinceId, selectedDistrict) {
		var $districtSelect = jQuery("#echbay_viettelpost_sender_district");
		var $wardSelect = jQuery("#echbay_viettelpost_sender_ward");

		if (!provinceId) {
			$districtSelect.html('<option value="">Chọn quận/huyện</option>');
			$wardSelect.html('<option value="">Chọn phường/xã</option>');
			return;
		}

		jQuery.ajax({
			url: echbay_viettelpost_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_get_districts",
				province_id: provinceId,
				nonce: echbay_viettelpost_ajax.nonce,
			},
			success: function (response) {
				$districtSelect.html('<option value="">Chọn quận/huyện</option>');
				$wardSelect.html('<option value="">Chọn phường/xã</option>');

				if (response.success && response.data) {
					jQuery.each(response.data, function (index, district) {
						var selected =
							selectedDistrict && selectedDistrict == district.DISTRICT_ID
								? " selected"
								: "";
						$districtSelect.append(
							'<option value="' +
								district.DISTRICT_ID +
								'"' +
								selected +
								">" +
								district.DISTRICT_NAME +
								"</option>"
						);
					});

					// If district was selected, load wards
					if (selectedDistrict && selectedDistrict !== "") {
						var selectedWard = $wardSelect.data("selected-value");
						loadWards(selectedDistrict, selectedWard);
					}
				}
			},
		});
	}

	// Function to load wards
	function loadWards(districtId, selectedWard) {
		var $wardSelect = jQuery("#echbay_viettelpost_sender_ward");

		if (!districtId) {
			$wardSelect.html('<option value="">Chọn phường/xã</option>');
			return;
		}

		jQuery.ajax({
			url: echbay_viettelpost_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_get_wards",
				district_id: districtId,
				nonce: echbay_viettelpost_ajax.nonce,
			},
			success: function (response) {
				$wardSelect.html('<option value="">Chọn phường/xã</option>');

				if (response.success && response.data) {
					jQuery.each(response.data, function (index, ward) {
						var selected =
							selectedWard && selectedWard == ward.WARDS_ID ? " selected" : "";
						$wardSelect.append(
							'<option value="' +
								ward.WARDS_ID +
								'"' +
								selected +
								">" +
								ward.WARDS_NAME +
								"</option>"
						);
					});
				}
			},
		});
	}

	// Initialize on page load with delay to ensure form is ready
	setTimeout(function () {
		initializeLocationDropdowns();
	}, 100);

	//
	jQuery(
		'label[for="echbay_viettelpost_test_connection"], label[for="echbay_viettelpost_sync_locations"]'
	).removeAttr("for");

	// Test connection button
	jQuery("#echbay_viettelpost_test_connection").on("click", function (e) {
		e.preventDefault();

		var $button = jQuery(this);
		var originalText = $button.text();

		$button
			.prop("disabled", true)
			.text(echbay_viettelpost_ajax.messages.testing);

		jQuery.ajax({
			url: echbay_viettelpost_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_test_connection",
				nonce: echbay_viettelpost_ajax.nonce,
			},
			success: function (response) {
				if (response.success) {
					alert("✓ " + response.data);
				} else {
					alert("✗ " + response.data);
				}
			},
			error: function () {
				alert("✗ Có lỗi xảy ra khi kiểm tra kết nối");
			},
			complete: function () {
				$button.prop("disabled", false).text(originalText);
			},
		});
	});

	// User List Inventory
	(function (userListInventory) {
		if (userListInventory) {
			console.log("userListInventory:", JSON.parse(userListInventory));
			// xong xóa luôn
			// sessionStorage.removeItem("echbay_viettelpost_user_list_inventory");
			// alert("✓ Đồng bộ địa chỉ thành công!");
		}
	})(sessionStorage.getItem("echbay_viettelpost_user_list_inventory"));

	// Sync locations button
	jQuery("#echbay_viettelpost_sync_locations").on("click", function (e) {
		e.preventDefault();

		var $button = jQuery(this);
		var originalText = $button.text();

		$button
			.prop("disabled", true)
			.text(echbay_viettelpost_ajax.messages.syncing);

		jQuery.ajax({
			url: echbay_viettelpost_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_sync_locations",
				inventory_id:
					jQuery("#echbay_viettelpost_sender_inventory").val() || "",
				nonce: echbay_viettelpost_ajax.nonce,
			},
			success: function (response) {
				if (response.success) {
					alert("✓ " + response.data.msg);
					sessionStorage.setItem(
						"echbay_viettelpost_user_list_inventory",
						JSON.stringify(response.data)
					);
					location.reload(); // Reload to update location dropdowns
				} else {
					alert("✗ " + response.data);
				}
			},
			error: function () {
				alert("✗ Có lỗi xảy ra khi đồng bộ địa chỉ");
			},
			complete: function () {
				$button.prop("disabled", false).text(originalText);
			},
		});
	});

	// Province change handler
	jQuery("#echbay_viettelpost_sender_province").on("change", function () {
		var provinceId = jQuery(this).val();
		loadDistricts(provinceId);
	});

	// District change handler
	jQuery("#echbay_viettelpost_sender_district").on("change", function () {
		var districtId = jQuery(this).val();
		loadWards(districtId);
	});

	// Smooth scroll to documentation sections
	jQuery(".viettelpost-docs-nav a").on("click", function (e) {
		e.preventDefault();
		var target = jQuery(this).attr("href");
		if (jQuery(target).length) {
			jQuery("html, body").animate(
				{
					scrollTop: jQuery(target).offset().top - 50,
				},
				500
			);
		}
	});

	// Search functionality in documentation
	jQuery("#viettelpost-docs-search").on("input", function () {
		var searchTerm = jQuery(this).val().toLowerCase();
		jQuery(".viettelpost-docs-section").each(function () {
			var sectionText = jQuery(this).text().toLowerCase();
			if (sectionText.indexOf(searchTerm) > -1 || searchTerm === "") {
				jQuery(this).show();
			} else {
				jQuery(this).hide();
			}
		});
	});

	// Documentation section toggle
	// console.log("Documentation section toggle initialized");
	jQuery(".viettelpost-docs-section h3").click(function () {
		jQuery(this).parent().toggleClass("collapsed");
	});

	// Collapse all sections by default except first one
	jQuery(".viettelpost-docs-section:not(:first)").addClass("collapsed");
});
