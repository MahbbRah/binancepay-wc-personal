<?php
/**
 * Plugin Name: Binance Personal Pay for WooCommerce
 * Plugin URI: https://github.com/open-source/woocommerce-binance-personal-pay
 * Description: Accept Binance Pay payments directly into your personal Binance account with automated transaction verification and zero middleman fees.
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
}
add_action( 'plugins_loaded', 'bpp_init_plugin' );

// Register activation and deactivation hooks.
require_once BPP_PLUGIN_DIR . 'includes/class-plugin.php';
register_activation_hook( __FILE__, array( 'BPP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BPP_Plugin', 'deactivate' ) );
