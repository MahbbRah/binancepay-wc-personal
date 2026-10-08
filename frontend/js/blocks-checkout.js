/**
 * Binance Personal Pay - WooCommerce Blocks Checkout Integration Script
 */
(function () {
	'use strict';

	if (!window.wc || !window.wc.wcBlocksRegistry || !window.wp || !window.wp.element) {
		return;
	}

	const { createElement } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities || { decodeEntities: (s) => s };
	const { __ } = window.wp.i18n || { __: (s) => s };

	const settings = window.wc.wcSettings ? window.wc.wcSettings.getSetting('binance_pay_data', {}) : {};
	const labelText = decodeEntities(settings.title || '') || __('Binance Pay', 'binance-personal-pay');
	const descriptionText = decodeEntities(settings.description || '') || __('Pay directly using Binance (USDT).', 'binance-personal-pay');
	const iconUrl = settings.icon || '';

	// Description inside checkout accordion
	const Content = () => {
		return createElement(
			'div',
			{ className: 'bpp-block-description', style: { padding: '8px 0', fontSize: '13px', color: '#475569' } },
			createElement('p', { style: { margin: '0 0 6px 0', lineHeight: '1.5' } }, descriptionText),
			createElement(
				'div',
				{
					style: {
						display: 'flex',
						alignItems: 'center',
						gap: '6px',
						color: '#0284c7',
						backgroundColor: '#f0f9ff',
						border: '1px solid #bae6fd',
						borderRadius: '6px',
						padding: '6px 10px',
						marginTop: '8px',
						fontSize: '12px',
						fontWeight: '500'
					}
				},
				createElement('span', null, '⚡'),
				createElement('span', null, __('Automated verification via Binance Pay. Exact amount and payment note provided after checkout.', 'binance-personal-pay'))
			)
		);
	};

	// Label in payment options list (with Icon + Title)
	const Label = () => {
		return createElement(
			'span',
			{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px', verticalAlign: 'middle' } },
			iconUrl ? createElement('img', {
				src: iconUrl,
				alt: 'Binance Pay',
				style: { height: '22px', width: 'auto', display: 'inline-block', verticalAlign: 'middle' }
			}) : createElement('span', { style: { fontSize: '16px' } }, '⚡'),
			createElement('span', { style: { fontWeight: '600' } }, labelText)
		);
	};

	/**
	 * Check if active checkout currency is USD.
	 * Evaluates cartData, window.wcSettings, and WOOCS.
	 */
	const isUsdCurrency = (cartData) => {
		// 1. Check cartTotals currency_code passed by WooCommerce Blocks
		if (cartData && cartData.cartTotals && cartData.cartTotals.currency_code) {
			return String(cartData.cartTotals.currency_code).toUpperCase() === 'USD';
		}

		// 2. Check window.wcSettings currency
		if (window.wcSettings && window.wcSettings.currency && window.wcSettings.currency.code) {
			return String(window.wcSettings.currency.code).toUpperCase() === 'USD';
		}

		// 3. Check wc.wcSettings.getSetting('currency')
		if (window.wc && window.wc.wcSettings && typeof window.wc.wcSettings.getSetting === 'function') {
			const currencySetting = window.wc.wcSettings.getSetting('currency', null);
			if (currencySetting && currencySetting.code) {
				return String(currencySetting.code).toUpperCase() === 'USD';
			}
		}

		// 4. Check WOOCS global variable
		if (typeof window.woocs_current_currency !== 'undefined') {
			const woocsCode = typeof window.woocs_current_currency === 'object' && window.woocs_current_currency !== null
				? (window.woocs_current_currency.name || window.woocs_current_currency.code)
				: window.woocs_current_currency;
			if (woocsCode) {
				return String(woocsCode).toUpperCase() === 'USD';
			}
		}

		// 5. Check WOOCS cookie
		const cookies = document.cookie ? document.cookie.split(';') : [];
		for (let i = 0; i < cookies.length; i++) {
			const c = cookies[i].trim();
			if (c.indexOf('woocs_current_currency=') === 0) {
				return c.substring('woocs_current_currency='.length).toUpperCase() === 'USD';
			}
		}

		return false;
	};

	const Block_Gateway = {
		name: 'binance_pay',
		label: createElement(Label),
		content: createElement(Content),
		edit: createElement(Content),
		canMakePayment: (cartData) => isUsdCurrency(cartData),
		ariaLabel: labelText,
		supports: {
			features: settings.supports || ['products'],
		},
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
})();
