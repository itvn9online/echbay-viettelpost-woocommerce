jQuery(document).ready(function ($) {
	// Track initialization state to prevent duplicate calls
	var isInitializing = false;
	var isRestoring = false;
	var initializationTimeout;

	// Initialize location dropdowns with debouncing
	function debouncedInitialization() {
		clearTimeout(initializationTimeout);
		initializationTimeout = setTimeout(function () {
			if (!isInitializing && !isRestoring) {
				isInitializing = true;
				// console.log("Starting initialization...");

				// Initialize fields first
				initializeLocationDropdowns();

				// Then restore saved selections after a small delay
				setTimeout(function () {
					isRestoring = true;
					restoreSavedSelections();
					setTimeout(function () {
						isInitializing = false;
						isRestoring = false;
						// console.log("Initialization complete.");
					}, 500);
				}, 50);
			}
		}, 100);
	}

	// Initial setup
	debouncedInitialization();

	// Handle country change to reinitialize fields
	$(document).on("change", 'select[name="billing_country"]', function () {
		// Give WooCommerce time to update fields, then restore selections
		setTimeout(function () {
			debouncedInitialization();
		}, 200);
	});

	$(document).on("change", 'select[name="shipping_country"]', function () {
		// Give WooCommerce time to update fields, then restore selections
		setTimeout(function () {
			debouncedInitialization();
		}, 200);
	});

	// Handle province change
	$(document).on("change", 'select[data-field-type="province"]', function () {
		var $provinceSelect = $(this);
		var provinceId = $provinceSelect.val();
		var fieldPrefix = $provinceSelect.attr("name").replace("_state", "");

		// Don't process if we're in initialization, restoration mode, or field-level restoration
		if (
			isInitializing ||
			isRestoring ||
			$provinceSelect.data("initializing") ||
			$provinceSelect.data("restoring-selection")
		) {
			console.log("Province change ignored - restoration in progress");
			return;
		}

		console.log("Processing manual province change:", provinceId, fieldPrefix);

		// Save selection to localStorage
		saveSelection(fieldPrefix + "_state", provinceId);

		// Reset dependent dropdowns
		var $districtSelect = $('select[name="' + fieldPrefix + '_city"]');
		// var $wardSelect = $('select[name="' + fieldPrefix + '_ward"]');
		var $wardSelect = $('select[name="' + fieldPrefix + '_address_2"]');

		$districtSelect.html(
			'<option value="">' +
				viettelpost_checkout_ajax.messages.select_district +
				"</option>"
		);
		$wardSelect.html(
			'<option value="">' +
				viettelpost_checkout_ajax.messages.select_ward +
				"</option>"
		);

		// Clear dependent selections since this is a manual change
		// clearDependentSelections(fieldPrefix);

		// Clear cached data for dependent dropdowns
		$districtSelect.removeData("last-loaded-province");
		$wardSelect.removeData("last-loaded-district");

		if (provinceId) {
			loadDistricts(provinceId, fieldPrefix);
		}

		// Trigger WooCommerce update
		$("body").trigger("update_checkout");
	});

	// Handle district change
	$(document).on("change", 'select[data-field-type="district"]', function () {
		var $districtSelect = $(this);
		var districtId = $districtSelect.val();
		var fieldPrefix = $districtSelect.attr("name").replace("_city", "");

		// Don't process if we're restoring
		if (isInitializing || isRestoring) {
			console.log("District change ignored - restoration in progress");
			return;
		}

		console.log("Processing manual district change:", districtId, fieldPrefix);

		// Save selection to localStorage
		saveSelection(fieldPrefix + "_city", districtId);

		// Reset ward dropdown
		// var $wardSelect = $('select[name="' + fieldPrefix + '_ward"]');
		var $wardSelect = $('select[name="' + fieldPrefix + '_address_2"]');
		$wardSelect.html(
			'<option value="">' +
				viettelpost_checkout_ajax.messages.select_ward +
				"</option>"
		);

		// Clear saved ward selection
		// saveSelection(fieldPrefix + "_address_2", "");

		// Clear cached data for ward dropdown
		$wardSelect.removeData("last-loaded-district");

		if (districtId) {
			loadWards(districtId, fieldPrefix);
		}

		// Trigger WooCommerce update
		$("body").trigger("update_checkout");
	});

	// Handle ward change
	$(document).on("change", 'select[data-field-type="ward"]', function () {
		var $wardSelect = $(this);
		var wardId = $wardSelect.val();
		var fieldPrefix = $wardSelect.attr("name").replace("_address_2", "");

		// Don't process if we're restoring
		if (isInitializing || isRestoring) {
			console.log("Ward change ignored - restoration in progress");
			return;
		}

		console.log("Processing manual ward change:", wardId, fieldPrefix);

		// Save selection to localStorage
		saveSelection(fieldPrefix + "_address_2", wardId);

		// Trigger WooCommerce update when ward changes
		$("body").trigger("update_checkout");
	});

	/**
	 * Initialize location dropdowns on page load
	 */
	function initializeLocationDropdowns() {
		// Initialize billing dropdowns
		initializeFieldSet("billing");

		// Initialize shipping dropdowns
		initializeFieldSet("shipping");
	}

	/**
	 * Initialize dropdowns for a field set (billing/shipping)
	 */
	function initializeFieldSet(prefix) {
		var $provinceSelect = $('select[name="' + prefix + '_state"]');
		var $districtSelect = $('select[name="' + prefix + '_city"]');
		// var $wardSelect = $('select[name="' + prefix + '_ward"]');
		var $wardSelect = $('select[name="' + prefix + '_address_2"]');

		if (
			$provinceSelect.length &&
			$districtSelect.length &&
			$wardSelect.length
		) {
			// Mark field set as initializing to prevent conflicts
			$provinceSelect.data("initializing", true);

			// var selectedProvince = $provinceSelect.val();
			var selectedDistrict = $districtSelect.val();
			var selectedWard = $wardSelect.val();

			// Store selected values for restoration (but don't load data here - let restoreSavedSelections handle it)
			$districtSelect.data("selected-value", selectedDistrict);
			$wardSelect.data("selected-value", selectedWard);

			// Remove initializing flag after a short delay
			setTimeout(function () {
				$provinceSelect.removeData("initializing");
			}, 100);
		}
	}

	/**
	 * Load districts for selected province
	 */
	function loadDistricts(provinceId, fieldPrefix, selectedDistrict, callback) {
		var $districtSelect = $('select[name="' + fieldPrefix + '_city"]');
		// var $wardSelect = $('select[name="' + fieldPrefix + '_ward"]');
		var $wardSelect = $('select[name="' + fieldPrefix + '_address_2"]');

		console.log(
			"loadDistricts called:",
			provinceId,
			fieldPrefix,
			selectedDistrict
		);

		// Check if districts already loaded for this province
		var lastLoadedProvince = $districtSelect.data("last-loaded-province");
		if (
			lastLoadedProvince == provinceId &&
			$districtSelect.find("option").length > 1
		) {
			// console.log("Districts already loaded for province:", provinceId);
			// Data already loaded, just restore selection if needed
			if (
				selectedDistrict &&
				$districtSelect.find('option[value="' + selectedDistrict + '"]')
					.length > 0
			) {
				$districtSelect.val(selectedDistrict);
				var selectedWard = $wardSelect.data("selected-value");
				loadWards(selectedDistrict, fieldPrefix, selectedWard);
			}
			if (callback) callback();
			return;
		}

		// Check if already loading for this province
		var loadingKey = "loading_districts_" + provinceId + "_" + fieldPrefix;
		if ($districtSelect.data(loadingKey)) {
			console.log("Already loading districts for:", provinceId, fieldPrefix);
			return; // Already loading, prevent duplicate request
		}

		// Mark as loading
		$districtSelect.data(loadingKey, true);

		// Show loading state
		$districtSelect.html(
			'<option value="">' +
				viettelpost_checkout_ajax.messages.loading +
				"</option>"
		);

		// Check localStorage for cached districts
		let a = localStorage.getItem(
			"viettelpost_get_" + fieldPrefix + "_districts"
		);
		if (a !== null) {
			// Parse and use cached districts
			let cachedDistricts = JSON.parse(a);

			if (
				typeof cachedDistricts.provinceId != "undefined" &&
				cachedDistricts.provinceId == provinceId
			) {
				console.log(
					"%c" + "Using cached districts:",
					"color: green",
					cachedDistricts.provinceId,
					provinceId,
					cachedDistricts
				);

				// Populate district select with cached data
				$districtSelect.html(
					'<option value="">' +
						viettelpost_checkout_ajax.messages.select_district +
						"</option>"
				);
				$.each(cachedDistricts.districts, function (index, district) {
					$districtSelect.append(
						'<option value="' +
							district.DISTRICT_ID +
							'">' +
							district.DISTRICT_NAME +
							"</option>"
					);
				});
				// Restore selection if needed
				// selectedDistrict = $districtSelect.val();
				console.log(
					"%c" + "Selected district:",
					"color: yellow",
					selectedDistrict
				);
				if (
					selectedDistrict &&
					selectedDistrict != "" &&
					$districtSelect.find('option[value="' + selectedDistrict + '"]')
						.length > 0
				) {
					$districtSelect.val(selectedDistrict);
					var selectedWard = $wardSelect.data("selected-value");
					loadWards(selectedDistrict, fieldPrefix, selectedWard);
				}
				// Execute callback if provided
				if (callback) callback();

				// Clear loading flag
				$districtSelect.removeData(loadingKey);

				return;
			}
		}

		console.log(
			"%c" + "Starting AJAX request for districts:",
			"color: orange",
			provinceId,
			fieldPrefix
		);

		// No cached data available, proceed with AJAX request
		$districtSelect.prop("disabled", true);

		//
		localStorage.removeItem("viettelpost_get_" + fieldPrefix + "_wards");
		localStorage.removeItem(
			"viettelpost_checkout_" + fieldPrefix + "_address_2"
		);
		console.log("%c" + "Cleared cached wards...", "color: violet");

		$.ajax({
			url: viettelpost_checkout_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_get_checkout_districts",
				province_id: provinceId,
				nonce: viettelpost_checkout_ajax.nonce,
			},
			success: function (response) {
				$districtSelect.html(
					'<option value="">' +
						viettelpost_checkout_ajax.messages.select_district +
						"</option>"
				);
				$wardSelect.html(
					'<option value="">' +
						viettelpost_checkout_ajax.messages.select_ward +
						"</option>"
				);

				if (response.success && response.data) {
					// Cache the districts
					localStorage.setItem(
						"viettelpost_get_" + fieldPrefix + "_districts",
						JSON.stringify({
							provinceId: provinceId,
							districts: response.data,
						})
					);

					// Populate district select with new data
					$.each(response.data, function (index, district) {
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

					// Store which province was loaded
					$districtSelect.data("last-loaded-province", provinceId);

					// If district was selected, load wards
					// selectedDistrict = $districtSelect.val();
					console.log(
						"%c" + "Selected district:",
						"color: yellow",
						selectedDistrict
					);
					if (selectedDistrict && selectedDistrict != "") {
						var selectedWard = $wardSelect.data("selected-value");
						loadWards(selectedDistrict, fieldPrefix, selectedWard);
					}

					// Execute callback if provided
					if (callback) callback();
				}
			},
			error: function () {
				$districtSelect.html(
					'<option value="">' +
						viettelpost_checkout_ajax.messages.select_district +
						"</option>"
				);
			},
			complete: function () {
				$districtSelect.prop("disabled", false);
				// Clear loading flag
				$districtSelect.removeData(loadingKey);
			},
		});
	}

	/**
	 * Load wards for selected district
	 */
	function loadWards(districtId, fieldPrefix, selectedWard, callback) {
		// var $wardSelect = $('select[name="' + fieldPrefix + '_ward"]');
		var $wardSelect = $('select[name="' + fieldPrefix + '_address_2"]');

		// Check if wards already loaded for this district
		var lastLoadedDistrict = $wardSelect.data("last-loaded-district");
		if (
			lastLoadedDistrict == districtId &&
			$wardSelect.find("option").length > 1
		) {
			// Data already loaded, just restore selection if needed
			if (
				selectedWard &&
				$wardSelect.find('option[value="' + selectedWard + '"]').length > 0
			) {
				$wardSelect.val(selectedWard);
			}
			if (callback) callback();
			return;
		}

		// Check if already loading for this district
		var loadingKey = "loading_wards_" + districtId + "_" + fieldPrefix;
		if ($wardSelect.data(loadingKey)) {
			return; // Already loading, prevent duplicate request
		}

		// Mark as loading
		$wardSelect.data(loadingKey, true);

		// Show loading state
		$wardSelect.html(
			'<option value="">' +
				viettelpost_checkout_ajax.messages.loading +
				"</option>"
		);

		// Check localStorage for cached wards
		let a = localStorage.getItem("viettelpost_get_" + fieldPrefix + "_wards");
		if (a !== null) {
			// Parse and use cached wards
			let cachedWards = JSON.parse(a);

			if (
				typeof cachedWards.districtId != "undefined" &&
				cachedWards.districtId == districtId
			) {
				console.log("%c" + "Using cached wards:", "color: green", cachedWards);

				// Populate ward select with cached data
				$wardSelect.html(
					'<option value="">' +
						viettelpost_checkout_ajax.messages.select_ward +
						"</option>"
				);
				$.each(cachedWards.wards, function (index, ward) {
					$wardSelect.append(
						'<option value="' +
							ward.WARDS_ID +
							'">' +
							ward.WARDS_NAME +
							"</option>"
					);
				});
				// Restore selection if needed
				if (
					selectedWard &&
					$wardSelect.find('option[value="' + selectedWard + '"]').length > 0
				) {
					$wardSelect.val(selectedWard);
				}
				// Execute callback if provided
				if (callback) callback();

				// Clear loading flag
				$wardSelect.removeData(loadingKey);

				return;
			}
		}

		console.log(
			"%c" + "Starting AJAX request for wards:",
			"color: orange",
			districtId,
			fieldPrefix
		);

		// Disable ward select while loading
		$wardSelect.prop("disabled", true);

		$.ajax({
			url: viettelpost_checkout_ajax.ajax_url,
			type: "POST",
			data: {
				action: "viettelpost_get_checkout_wards",
				district_id: districtId,
				nonce: viettelpost_checkout_ajax.nonce,
			},
			success: function (response) {
				$wardSelect.html(
					'<option value="">' +
						viettelpost_checkout_ajax.messages.select_ward +
						"</option>"
				);

				if (response.success && response.data) {
					// Cache the wards
					localStorage.setItem(
						"viettelpost_get_" + fieldPrefix + "_wards",
						JSON.stringify({
							districtId: districtId,
							wards: response.data,
						})
					);

					// Populate ward select with new data
					$.each(response.data, function (index, ward) {
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

					// Store which district was loaded
					$wardSelect.data("last-loaded-district", districtId);

					// Execute callback if provided
					if (callback) callback();
				}
			},
			error: function () {
				$wardSelect.html(
					'<option value="">' +
						viettelpost_checkout_ajax.messages.select_ward +
						"</option>"
				);
			},
			complete: function () {
				$wardSelect.prop("disabled", false);
				// Clear loading flag
				$wardSelect.removeData(loadingKey);
			},
		});
	}

	// Re-initialize when checkout is updated (with enhanced debouncing)
	var reinitTimeout;
	$(document.body).on("updated_checkout", function () {
		clearTimeout(reinitTimeout);
		reinitTimeout = setTimeout(function () {
			debouncedInitialization();
		}, 200);
	});

	// Also run when body is updated (for themes that rebuild checkout)
	var domUpdateTimeout;
	$(document.body).on("DOMNodeInserted", function (e) {
		if (
			$(e.target).hasClass("woocommerce-checkout") ||
			$(e.target).find(".woocommerce-checkout").length
		) {
			clearTimeout(domUpdateTimeout);
			domUpdateTimeout = setTimeout(function () {
				debouncedInitialization();
			}, 400);
		}
	});

	// Handle "Ship to different address" toggle
	$(document).on("change", "#ship-to-different-address-checkbox", function () {
		if ($(this).is(":checked")) {
			setTimeout(function () {
				initializeFieldSet("shipping");
				restoreSavedSelections("shipping");
			}, 150);
		}
	});

	/**
	 * Save selection to localStorage
	 */
	function saveSelection(fieldName, value) {
		try {
			var storageKey = "viettelpost_checkout_" + fieldName;
			if (value) {
				localStorage.setItem(storageKey, value);
				console.log("Saved to localStorage:", storageKey, "=", value);
			} else {
				localStorage.removeItem(storageKey);
				console.log("Removed from localStorage:", storageKey);
			}
		} catch (e) {
			// localStorage not available, fail silently
			console.log("localStorage not available:", e);
		}
	}

	/**
	 * Get saved selection from localStorage
	 */
	function getSavedSelection(fieldName) {
		try {
			var storageKey = "viettelpost_checkout_" + fieldName;
			var value = localStorage.getItem(storageKey);
			// console.log("Retrieved from localStorage:", storageKey, "=", value);
			return value;
		} catch (e) {
			// localStorage not available, return null
			// console.log("localStorage not available:", e);
			return null;
		}
	}

	/**
	 * Clear dependent selections when parent changes
	 */
	function clearDependentSelections(fieldPrefix) {
		saveSelection(fieldPrefix + "_city", "");
		saveSelection(fieldPrefix + "_address_2", "");
	}

	/**
	 * Restore saved selections from localStorage
	 */
	function restoreSavedSelections(specificPrefix) {
		var prefixes = specificPrefix ? [specificPrefix] : ["billing", "shipping"];
		// console.log("Starting restoration for prefixes:", prefixes);

		prefixes.forEach(function (prefix) {
			// Restore province selection
			var savedProvince = getSavedSelection(prefix + "_state");
			var $provinceSelect = $('select[name="' + prefix + '_state"]');
			if (!savedProvince) {
				savedProvince = $provinceSelect.val();
			}
			// console.log("Restoring province for", prefix, ":", savedProvince);

			if (savedProvince) {
				if (
					$provinceSelect.length &&
					$provinceSelect.find('option[value="' + savedProvince + '"]').length >
						0
				) {
					// Mark as restoring to prevent clearing localStorage
					$provinceSelect.data("restoring-selection", true);

					// Set value (change events will be ignored due to global flags)
					$provinceSelect.val(savedProvince);

					// Load districts and restore district selection
					var savedDistrict = getSavedSelection(prefix + "_city");
					// console.log("Restoring district for", prefix, ":", savedDistrict);

					if (savedDistrict) {
						loadDistricts(savedProvince, prefix, savedDistrict, function () {
							// After districts loaded, restore ward selection
							// var savedWard = getSavedSelection(prefix + "_ward");
							var savedWard = getSavedSelection(prefix + "_address_2");
							// console.log("Restoring ward for", prefix, ":", savedWard);
							if (savedWard) {
								loadWards(savedDistrict, prefix, savedWard);
							}
						});
					} else {
						loadDistricts(savedProvince, prefix);
					}

					// Remove restoring flag after longer delay
					setTimeout(function () {
						$provinceSelect.removeData("restoring-selection");
					}, 1000);
				}
			}
		});
	}
});
