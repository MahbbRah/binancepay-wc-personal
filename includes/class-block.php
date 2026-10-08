<?php
/**
 * WooCommerce Blocks Checkout Integration for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPP_Gateway_Blocks
 *
 * Registers Binance Pay within WooCommerce Blocks Checkout.
 */
final class BPP_Gateway_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name matching gateway ID.
	 *
	 * @var string
	 */
	protected $name = 'binance_pay';

	/**
	 * Gateway settings.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Initialize the payment method type.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_binance_pay_settings', array() );
	}

	/**
	 * Check if payment method is active and available.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		if ( 'yes' !== ( $this->settings['enabled'] ?? 'no' ) ) {
			return false;
		}

		// Gateway strictly operates with USD currency.
		if ( class_exists( 'WC_Gateway_Binance_Personal_Pay' ) ) {
			if ( 'USD' !== WC_Gateway_Binance_Personal_Pay::get_current_currency() ) {
				return false;
			}
		}

		$api_key     = $this->settings['api_key'] ?? '';
		$api_secret  = $this->settings['api_secret'] ?? '';
		$merchant_id = $this->settings['merchant_binance_id'] ?? '';

		if ( empty( $api_key ) || empty( $api_secret ) || empty( $merchant_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features(): array {
		return array( 'products' );
	}

	/**
	 * Script handles for checkout block.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		$script_url  = BPP_PLUGIN_URL . 'frontend/js/blocks-checkout.js';
		$script_path = BPP_PLUGIN_DIR . 'frontend/js/blocks-checkout.js';
		$file_mtime  = file_exists( $script_path ) ? filemtime( $script_path ) : BPP_VERSION;

		wp_register_script(
			'bpp-blocks-checkout',
			$script_url,
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			BPP_VERSION . '-' . $file_mtime,
			true
		);

		return array( 'bpp-blocks-checkout' );
	}

	/**
	 * Data passed to checkout block script.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		return array(
			'title'       => $this->settings['title'] ?? __( 'Binance Pay', 'binance-personal-pay' ),
			'description' => $this->settings['description'] ?? __( 'Pay directly using Binance (USDT).', 'binance-personal-pay' ),
			'icon'        => BPP_PLUGIN_URL . 'frontend/images/binance-pay.svg',
			'supports'    => array( 'products' ),
		);
	}
}
