/**
 * Binance Personal Pay - Admin Scripts
 */

(function ($) {
	'use strict';

	$(document).ready(function () {

		// 1. Test Binance Connection Handler (Gateway settings screen)
		$('#bpp-btn-test-connection').on('click', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const $spinner = $('#bpp-test-spinner');
			const $result = $('#bpp-test-result');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.html('<span style="color:#64748b;">' + (bppAdmin.strings.testing || 'Testing connection to Binance...') + '</span>');

			const apiKey = $('#woocommerce_binance_pay_api_key').val();
			const apiSecret = $('#woocommerce_binance_pay_api_secret').val();
			const merchantId = $('#woocommerce_binance_pay_merchant_binance_id').val();

			$.ajax({
				url: bppAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bpp_test_connection',
					security: bppAdmin.nonce,
					api_key: apiKey,
					api_secret: apiSecret,
					merchant_binance_id: merchantId
				},
				success: function (res) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					if (res && res.success) {
						$result.html('<span style="color:#16a34a;">' + res.data.message + '</span>');
					} else {
						const err = (res && res.data && res.data.message) ? res.data.message : 'Connection test failed.';
						$result.html('<span style="color:#dc2626;">' + err + '</span>');
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					$result.html('<span style="color:#dc2626;">AJAX request failed. Check server error logs.</span>');
				}
			});
		});

		// 2. Scan Now Handler (Transactions screen)
		$('#bpp-btn-scan-now').on('click', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const $spinner = $('#bpp-scan-spinner');
			const $notice = $('#bpp-scan-notice');
			const $noticeText = $('#bpp-scan-notice-text');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: bppAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bpp_scan_now',
					security: bppAdmin.nonce
				},
				success: function (res) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					if (res && res.success) {
						$notice.removeClass('notice-error').addClass('notice-success').show();
						$noticeText.html('<pre style="margin:0; font-family:inherit;">' + res.data.message + '</pre>');
						setTimeout(function () {
							window.location.reload();
						}, 2500);
					} else {
						const err = (res && res.data && res.data.message) ? res.data.message : 'Scan failed.';
						$notice.removeClass('notice-success').addClass('notice-error').show();
						$noticeText.text(err);
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					$notice.removeClass('notice-success').addClass('notice-error').show();
					$noticeText.text('Network error executing scan.');
				}
			});
		});

		// 3. Raw Transaction Details Modal
		$(document).on('click', '.bpp-view-details', function (e) {
			e.preventDefault();
			const rawJson = $(this).data('json');
			try {
				const parsed = (typeof rawJson === 'string') ? JSON.parse(rawJson) : rawJson;
				$('#bpp-modal-json').text(JSON.stringify(parsed, null, 2));
			} catch (err) {
				$('#bpp-modal-json').text(String(rawJson));
			}
			$('#bpp-modal').fadeIn(150);
		});

		$(document).on('click', '.bpp-modal-close, .bpp-modal-backdrop', function (e) {
			if ($(e.target).hasClass('bpp-modal-backdrop') || $(e.target).hasClass('bpp-modal-close')) {
				$('#bpp-modal').fadeOut(150);
			}
		});

	});

})(jQuery);
