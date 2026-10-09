<?php
/**
 * Plugin Name: Personal Payment Gateway for Binance on WooCommerce
 * Plugin URI: https://github.com/open-source/woocommerce-binance-personal-pay
 * Description: Accept Binance Pay crypto payments directly into your personal Binance account on WooCommerce with automated transaction verification and zero middleman fees.
 * Version: 0.1.0
 * Author: Open Source Community
 * Author URI: https://github.com/open-source/woocommerce-binance-personal-pay
 * Text Domain: binance-personal-pay
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 7.0
 * WC tested up to: 9.3
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'BPP_VERSION', '0.1.0' );
define( 'BPP_PLUGIN_FILE', __FILE__ );
define( 'BPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Notice: This plugin is an independent open-source project and is not affiliated with or endorsed by Binance.
 */

// Verify PHP version requirement.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Binance Personal Pay requires PHP version 8.1 or higher. Please upgrade your PHP installation.', 'binance-personal-pay' ); ?></p>
			</div>
			<?php
		}
	);
	return;
}

/**
 * Declare HPOS and Cart/Checkout Blocks compatibility.
 * Must run before woocommerce_init.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BPP_PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BPP_PLUGIN_FILE, true );
		}
	}
);

/**
 * Initialize the plugin after all plugins are loaded.
 *
 * @return void
 */
function bpp_init_plugin(): void {
	// Check if WooCommerce is installed and active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Binance Personal Pay requires WooCommerce to be installed and active.', 'binance-personal-pay' ); ?></p>
				</div>
				<?php
			}
		);
		return;
	}

	// Load core plugin class.
	require_once BPP_PLUGIN_DIR . 'includes/class-plugin.php';

	// Bootstrap plugin.
	BPP_Plugin::instance()->init();

	// Fallback if blocks were already loaded before plugins_loaded.
	if ( did_action( 'woocommerce_blocks_loaded' ) ) {
		bpp_register_blocks_support();
	}
}
add_action( 'plugins_loaded', 'bpp_init_plugin' );

// Register activation and deactivation hooks.
require_once BPP_PLUGIN_DIR . 'includes/class-plugin.php';
register_activation_hook( __FILE__, array( 'BPP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BPP_Plugin', 'deactivate' ) );

/**
 * Register WooCommerce Blocks checkout support.
 *
 * @return void
 */
function bpp_register_blocks_support(): void {
	static $registered = false;
	if ( $registered ) {
		return;
	}

	if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}

	$registered = true;
	require_once BPP_PLUGIN_DIR . 'includes/class-block.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
			$payment_method_registry->register( new BPP_Gateway_Blocks() );
		}
	);
}
add_action( 'woocommerce_blocks_loaded', 'bpp_register_blocks_support' );

/**
 * Enforce USD currency: hide binance_pay on checkout when currency is not USD.
 */
add_filter(
	'woocommerce_available_payment_gateways',
	function ( $gateways ) {
		if ( isset( $gateways['binance_pay'] ) ) {
			if ( class_exists( 'WC_Gateway_Binance_Personal_Pay' ) && 'USD' !== WC_Gateway_Binance_Personal_Pay::get_current_currency() ) {
				unset( $gateways['binance_pay'] );
			}
		}
		return $gateways;
	},
	50
);

/**
 * Gateway icon filter for standard checkout rendering.
 */
add_filter(
	'woocommerce_gateway_icon',
	function ( $icon, $gateway_id ) {
		if ( 'binance_pay' === $gateway_id ) {
			$icon_url = BPP_PLUGIN_URL . 'frontend/images/binance-pay.svg';
			$icon     = '<img src="' . esc_url( $icon_url ) . '" alt="Binance Pay" style="height:22px; width:auto; margin-left:8px; vertical-align:middle;">';
		}
		return $icon;
	},
	10,
	2
);
