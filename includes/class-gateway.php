<?php
/**
 * WooCommerce Payment Gateway for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_Binance_Personal_Pay
 *
 * Implements the standard WooCommerce payment gateway lifecycle for personal Binance Pay.
 */
class WC_Gateway_Binance_Personal_Pay extends WC_Payment_Gateway {

	/**
	 * Gateway identifier.
	 */
	public const GATEWAY_ID = 'binance_pay';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = self::GATEWAY_ID;
		$this->icon               = apply_filters( 'bpp_gateway_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'Binance Personal Pay', 'binance-personal-pay' );
		$this->method_description = __( 'Accept Binance Pay payments directly into your personal Binance account without merchant verification.', 'binance-personal-pay' );
		$this->supports           = array( 'products' );

		// Load form fields and settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Binance Pay', 'binance-personal-pay' ) );
		$this->description = $this->get_option( 'description', __( 'Pay directly using Binance.', 'binance-personal-pay' ) );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize Gateway settings form fields in WooCommerce admin.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'binance-personal-pay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Binance Personal Pay', 'binance-personal-pay' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'binance-personal-pay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'binance-personal-pay' ),
				'default'     => __( 'Binance Pay', 'binance-personal-pay' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'binance-personal-pay' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on checkout.', 'binance-personal-pay' ),
				'default'     => __( 'Pay directly using Binance.', 'binance-personal-pay' ),
				'desc_tip'    => true,
			),
			'api_credentials_heading' => array(
				'title'       => __( 'Binance Personal Account Credentials', 'binance-personal-pay' ),
				'type'        => 'title',
				'description' => __( 'Create an API key from your personal Binance account (API Management). Ensure you grant ONLY Read-Only permissions. Never enable trading or withdrawals.', 'binance-personal-pay' ),
			),
			'api_key' => array(
				'title'       => __( 'Binance API Key', 'binance-personal-pay' ),
				'type'        => 'text',
				'description' => __( 'Personal Binance API key.', 'binance-personal-pay' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'api_secret' => array(
				'title'       => __( 'Binance API Secret', 'binance-personal-pay' ),
				'type'        => 'password',
				'description' => __( 'Personal Binance API secret. Kept strictly on your server and never revealed.', 'binance-personal-pay' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_binance_id' => array(
				'title'       => __( 'Merchant Binance ID', 'binance-personal-pay' ),
				'type'        => 'text',
				'description' => __( 'Your personal Binance Pay ID / User ID (numeric UID) where customers will send payments.', 'binance-personal-pay' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_connection_btn' => array(
				'type' => 'bpp_test_connection_btn',
			),
			'payment_config_heading' => array(
				'title'       => __( 'Payment Configuration', 'binance-personal-pay' ),
				'type'        => 'title',
				'description' => __( 'Configure payment matching rules and timeout.', 'binance-personal-pay' ),
			),
			'currency' => array(
				'title'       => __( 'Accepted Currency', 'binance-personal-pay' ),
				'type'        => 'select',
				'description' => __( 'Cryptocurrency for checkout. In v0.1, USDT is fully supported.', 'binance-personal-pay' ),
				'default'     => 'USDT',
				'options'     => array(
					'USDT' => 'USDT (Tether)',
					'BUSD' => 'BUSD',
					'USDC' => 'USDC',
				),
			),
			'payment_timeout' => array(
				'title'       => __( 'Payment Timeout (Minutes)', 'binance-personal-pay' ),
				'type'        => 'number',
				'description' => __( 'Countdown timer duration on the customer payment page (default 10 minutes).', 'binance-personal-pay' ),
				'default'     => 10,
				'custom_attributes' => array(
					'min'  => 1,
					'max'  => 1440,
					'step' => 1,
				),
			),
			'timeout_action' => array(
				'title'       => __( 'After Payment Timeout', 'binance-personal-pay' ),
				'type'        => 'select',
				'description' => __( 'Default is to keep order pending to prevent canceling payments delayed by network confirmation.', 'binance-personal-pay' ),
				'default'     => 'keep_pending',
				'options'     => array(
					'keep_pending' => __( 'Keep order pending (Recommended)', 'binance-personal-pay' ),
					'mark_failed'  => __( 'Mark order failed', 'binance-personal-pay' ),
					'cancel_order' => __( 'Cancel order', 'binance-personal-pay' ),
				),
			),
			'enable_auto_scan' => array(
				'title'   => __( 'Automatic Background Detection', 'binance-personal-pay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Periodically scan Binance Pay transactions in the background via Action Scheduler', 'binance-personal-pay' ),
				'default' => 'yes',
			),
			'enable_manual_verify' => array(
				'title'   => __( 'Manual Verification Button', 'binance-personal-pay' ),
				'type'    => 'checkbox',
				'label'   => __( "Display 'I\'ve Completed Payment' button on payment page", 'binance-personal-pay' ),
				'default' => 'yes',
			),
			'scanner_heading' => array(
				'title' => __( 'Scanner Settings', 'binance-personal-pay' ),
				'type'  => 'title',
			),
			'scanner_interval' => array(
				'title'       => __( 'Scanner Interval (Seconds)', 'binance-personal-pay' ),
				'type'        => 'number',
				'description' => __( 'Background scanner interval (approx. 60 seconds).', 'binance-personal-pay' ),
				'default'     => 60,
				'custom_attributes' => array(
					'min'  => 30,
					'max'  => 3600,
					'step' => 10,
				),
			),
			'lookback_minutes' => array(
				'title'       => __( 'Lookback Window (Minutes)', 'binance-personal-pay' ),
				'type'        => 'number',
				'description' => __( 'Lookback history to scan on each run (default 15 minutes).', 'binance-personal-pay' ),
				'default'     => 15,
				'custom_attributes' => array(
					'min'  => 5,
					'max'  => 1440,
					'step' => 5,
				),
			),
			'delete_data_on_uninstall' => array(
				'title'   => __( 'Uninstall Data Cleanup', 'binance-personal-pay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Delete plugin database table and settings when uninstalled. (Orders are never deleted)', 'binance-personal-pay' ),
				'default' => 'no',
			),
			'debug_log' => array(
				'title'   => __( 'Debug Logging', 'binance-personal-pay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Log debug messages to WooCommerce status logs', 'binance-personal-pay' ),
				'default' => 'yes',
			),
		);
	}

	/**
	 * Save admin options while keeping API secret secure if unmodified.
	 *
	 * @return bool
	 */
	public function process_admin_options(): bool {
		$post_data = $this->get_post_data();
		$saved     = BPP_Settings::save_settings( $post_data );

		// Update gateway settings cache.
		$this->init_settings();

		// Reschedule scanner if auto-scan setting or interval changed.
		if ( BPP_Settings::is_auto_scan_enabled() ) {
			BPP_Scheduler::schedule_scanner();
		} else {
			BPP_Scheduler::unschedule_scanner();
		}

		return true;
	}

	/**
	 * Check if gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		$is_available = parent::is_available();
		if ( ! $is_available ) {
			return false;
		}

		// Ensure store/checkout currency is USD.
		$current_currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		if ( 'USD' !== strtoupper( $current_currency ) ) {
			return false;
		}

		// Check required credentials.
		$api_key     = BPP_Settings::get_api_key();
		$api_secret  = BPP_Settings::get_api_secret();
		$merchant_id = BPP_Settings::get_merchant_binance_id();

		if ( empty( $api_key ) || empty( $api_secret ) || empty( $merchant_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Process payment on checkout submission.
	 *
	 * @param int $order_id WooCommerce Order ID.
	 * @return array{result: string, redirect: string}
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		// Enforce USD order currency.
		if ( 'USD' !== strtoupper( $order->get_currency() ) ) {
			wc_add_notice( __( 'Binance Pay is only available for orders placed in USD currency.', 'binance-personal-pay' ), 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		// Initialize order with deterministic reference plan-{order_id} and expiry timestamp.
		BPP_Order_Handler::initialize_order( $order );

		// Set order status to pending payment.
		$order->update_status( 'pending', __( 'Awaiting Binance Pay payment confirmation.', 'binance-personal-pay' ) );

		// Empty cart.
		WC()->cart->empty_cart();

		// Dedicated customer payment page URL.
		$payment_page_url = BPP_Payment_Page::get_payment_url( $order );

		return array(
			'result'   => 'success',
			'redirect' => $payment_page_url,
		);
	}
}
