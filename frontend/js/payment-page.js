/**
 * Binance Personal Pay - Frontend Payment Page Controller
 */

(function ($) {
	'use strict';

	if (typeof bppData === 'undefined') {
		return;
	}

	const BPP = {
		isChecking: false,
		pollTimer: null,
		countdownTimer: null,
		timeLeft: parseInt(bppData.timeLeft, 10) || 0,
		isPaid: !!bppData.isPaid,

		init: function () {
			this.bindEvents();
			this.startCountdown();

			if (!this.isPaid) {
				this.startPolling();
			} else {
				this.handlePaymentSuccess(bppData.redirectUrl);
			}
		},

		bindEvents: function () {
			const self = this;

			// Clipboard copy button handlers.
			$(document).on('click', '.bpp-btn-copy', function (e) {
				e.preventDefault();
				const targetId = $(this).data('target');
				const inputEl = document.getElementById(targetId);
				if (!inputEl) return;

				inputEl.select();
				inputEl.setSelectionRange(0, 99999);

				const textToCopy = inputEl.value;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(textToCopy);
				} else {
					document.execCommand('copy');
				}

				const $btn = $(this);
				const originalText = $btn.find('.bpp-copy-text').text();
				$btn.addClass('copied');
				$btn.find('.bpp-copy-text').text(bppData.strings.copied || 'Copied!');

				setTimeout(function () {
					$btn.removeClass('copied');
					$btn.find('.bpp-copy-text').text(originalText);
				}, 2000);
			});

			// Manual "I've Completed Payment" button handler.
			$('#bpp-btn-manual-verify').on('click', function (e) {
				e.preventDefault();
				self.triggerManualVerify();
			});

			// Fallback accordion toggle.
			$('#bpp-toggle-fallback').on('click', function (e) {
				e.preventDefault();
				const $content = $('#bpp-fallback-content');
				const isVisible = $content.is(':visible');
				$content.slideToggle(200);
				$(this).find('.bpp-accordion-arrow').text(isVisible ? '▼' : '▲');
			});

			// Binance Order ID Fallback verification handler.
			$('#bpp-btn-verify-fallback').on('click', function (e) {
				e.preventDefault();
				self.triggerFallbackVerify();
			});
		},

		startCountdown: function () {
			const self = this;
			const $display = $('#bpp-countdown');

			function update() {
				if (self.timeLeft <= 0) {
					$display.text('00:00');
					clearInterval(self.countdownTimer);
					self.handleTimeout();
					return;
				}

				const minutes = Math.floor(self.timeLeft / 60);
				const seconds = self.timeLeft % 60;
				const formatted = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
				$display.text(formatted);

				self.timeLeft--;
			}

			update();
			this.countdownTimer = setInterval(update, 1000);
		},

		startPolling: function () {
			const self = this;
			if (this.pollTimer) {
				clearInterval(this.pollTimer);
			}

			const interval = parseInt(bppData.pollInterval, 10) || 5000;
			this.pollTimer = setInterval(function () {
				self.checkStatus();
			}, interval);
		},

		stopPolling: function () {
			if (this.pollTimer) {
				clearInterval(this.pollTimer);
				this.pollTimer = null;
			}
		},

		checkStatus: function () {
			const self = this;
			if (this.isChecking || this.isPaid) {
				return;
			}

			this.isChecking = true;

			$.ajax({
				url: bppData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bpp_check_payment_status',
					security: bppData.nonce,
					order_id: bppData.orderId,
					order_key: bppData.orderKey
				},
				success: function (res) {
					self.isChecking = false;
					if (res && res.success && res.data) {
						if (res.data.status === 'paid') {
							self.handlePaymentSuccess(res.data.redirect_url);
						} else if (res.data.status === 'expired') {
							self.showAlert(res.data.message || bppData.strings.timeout, 'warning');
						}
					}
				},
				error: function () {
					self.isChecking = false;
					// Silently fail network error during regular poll; will retry next interval.
				}
			});
		},

		triggerManualVerify: function () {
			const self = this;
			const $btn = $('#bpp-btn-manual-verify');
			if ($btn.prop('disabled')) return;

			$btn.prop('disabled', true);
			const originalText = $btn.text();
			$btn.text(bppData.strings.verifying || 'Verifying with Binance...');

			$.ajax({
				url: bppData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bpp_manual_verify',
					security: bppData.nonce,
					order_id: bppData.orderId,
					order_key: bppData.orderKey
				},
				success: function (res) {
					if (res && res.success && res.data && res.data.status === 'paid') {
						self.handlePaymentSuccess(res.data.redirect_url);
					} else {
						const msg = (res && res.data && res.data.message) ? res.data.message : bppData.strings.notDetected;
						self.showAlert(msg, 'info');
						setTimeout(function () {
							$btn.prop('disabled', false).text(originalText);
						}, 4000);
					}
				},
				error: function () {
					self.showAlert(bppData.strings.unexpectedError, 'error');
					setTimeout(function () {
						$btn.prop('disabled', false).text(originalText);
					}, 3000);
				}
			});
		},

		triggerFallbackVerify: function () {
			const self = this;
			const $input = $('#bpp-fallback-order-id');
			const $btn = $('#bpp-btn-verify-fallback');
			const orderId = $.trim($input.val());

			if (!orderId) {
				self.showAlert(bppData.strings.fallbackEmpty, 'warning');
				$input.focus();
				return;
			}

			$btn.prop('disabled', true).text(bppData.strings.verifying || 'Verifying...');

			$.ajax({
				url: bppData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bpp_fallback_verify',
					security: bppData.nonce,
					order_id: bppData.orderId,
					order_key: bppData.orderKey,
					binance_order_id: orderId
				},
				success: function (res) {
					$btn.prop('disabled', false).text('Verify Payment');
					if (res && res.success && res.data && res.data.status === 'paid') {
						self.handlePaymentSuccess(res.data.redirect_url);
					} else {
						const msg = (res && res.data && res.data.message) ? res.data.message : bppData.strings.fallbackFailed;
						self.showAlert(msg, 'error');
					}
				},
				error: function () {
					$btn.prop('disabled', false).text('Verify Payment');
					self.showAlert(bppData.strings.unexpectedError, 'error');
				}
			});
		},

		handlePaymentSuccess: function (redirectUrl) {
			this.isPaid = true;
			this.stopPolling();
			if (this.countdownTimer) {
				clearInterval(this.countdownTimer);
			}

			// Update UI indicators.
			$('#bpp-status-indicator').addClass('paid');
			$('#bpp-status-text').text(bppData.strings.paymentSuccess || '✓ Payment verified! Redirecting...');
			$('#bpp-btn-manual-verify').hide();
			$('.bpp-auto-hint').hide();

			this.showAlert(bppData.strings.paymentSuccess || '✓ Payment verified! Redirecting...', 'success');

			// Automatic redirect to order confirmation.
			const targetUrl = redirectUrl || bppData.redirectUrl;
			if (targetUrl) {
				setTimeout(function () {
					window.location.href = targetUrl;
				}, 1500);
			}
		},

		handleTimeout: function () {
			this.checkStatus();
		},

		showAlert: function (msg, type) {
			const $alert = $('#bpp-alert-box');
			$alert.removeClass('bpp-alert-success bpp-alert-info bpp-alert-warning bpp-alert-error');
			$alert.addClass('bpp-alert-' + type);
			$alert.text(msg).slideDown(200);
		}
	};

	$(document).ready(function () {
		BPP.init();
	});

})(jQuery);
