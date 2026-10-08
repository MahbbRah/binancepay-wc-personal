<?php
/**
 * Core plugin class for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Plugin
 *
 * Coordinates plugin components, lifecycle hooks, and gateway registration.
 */
class BPP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var BPP_Plugin|null
	 */
	private static ?BPP_Plugin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return BPP_Plugin
	 */
	public static function instance(): BPP_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Private for singleton pattern.
	}

	/**
	 * Initialize plugin hooks and load components.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_dependencies();

		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		// Register WooCommerce payment gateway.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );

		// Initialize components.
		BPP_Scheduler::init();
		BPP_Payment_Page::init();
		BPP_Ajax::init();

		if ( is_admin() ) {
			BPP_Admin::init();
			add_filter( 'plugin_action_links_' . BPP_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
		}
	}

	/**
	 * Load all required class files.
	 *
	 * @return void
	 */
	public static function load_dependencies(): void {
		$includes_dir = BPP_PLUGIN_DIR . 'includes/';

		require_once BPP_PLUGIN_DIR . 'database/class-schema.php';
		require_once $includes_dir . 'class-money.php';
		require_once $includes_dir . 'class-logger.php';
		require_once $includes_dir . 'class-auth.php';
		require_once $includes_dir . 'class-binance-api.php';
		require_once $includes_dir . 'class-settings.php';
		require_once $includes_dir . 'class-order-handler.php';
		require_once $includes_dir . 'class-payment-matcher.php';
		require_once $includes_dir . 'class-transaction-scanner.php';
		require_once $includes_dir . 'class-payment-status.php';
		require_once $includes_dir . 'class-scheduler.php';
		require_once $includes_dir . 'class-gateway.php';
		require_once BPP_PLUGIN_DIR . 'frontend/class-payment-page.php';
		require_once BPP_PLUGIN_DIR . 'frontend/class-ajax.php';

		if ( is_admin() ) {
			require_once BPP_PLUGIN_DIR . 'admin/class-admin.php';
		}
	}

	/**
	 * Declare WooCommerce High-Performance Order Storage (HPOS) compatibility.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BPP_PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BPP_PLUGIN_FILE, true );
		}
	}

	/**
	 * Register the Binance Personal Pay gateway class into WooCommerce.
	 *
	 * @param array $gateways Registered gateway classes.
	 * @return array
	 */
	public function register_gateway( array $gateways ): array {
		$gateways[] = 'WC_Gateway_Binance_Personal_Pay';
		return $gateways;
	}

	/**
	 * Add Settings and Transactions action links to the plugins management screen.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=binance_pay' );
		$tx_url       = admin_url( 'admin.php?page=binance-payments' );

		$custom_links = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'binance-personal-pay' ) . '</a>',
			'<a href="' . esc_url( $tx_url ) . '">' . esc_html__( 'Transactions', 'binance-personal-pay' ) . '</a>',
		);

		return array_merge( $custom_links, $links );
	}

	/**
	 * Plugin activation lifecycle routine.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::load_dependencies();

		BPP_Schema::create_tables();

		if ( BPP_Settings::is_auto_scan_enabled() ) {
			BPP_Scheduler::schedule_scanner();
		}
	}

	/**
	 * Plugin deactivation lifecycle routine.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::load_dependencies();

		BPP_Scheduler::unschedule_scanner();
	}
}
