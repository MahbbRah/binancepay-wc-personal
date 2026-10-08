<?php
/**
 * Admin management and transaction screens for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Admin
 *
 * Handles the WooCommerce admin menu, transactions table, AJAX connection test, and manual scanner triggers.
 */
class BPP_Admin {

	/**
	 * Hook initialization.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// AJAX: Test connection.
		add_action( 'wp_ajax_bpp_test_connection', array( __CLASS__, 'handle_test_connection' ) );

		// AJAX: Scan now.
		add_action( 'wp_ajax_bpp_scan_now', array( __CLASS__, 'handle_scan_now' ) );

		// Custom gateway admin field for Test Connection button.
		add_action( 'woocommerce_admin_field_bpp_test_connection_btn', array( __CLASS__, 'render_test_connection_field' ) );
	}

	/**
	 * Register the Binance Payments submenu under WooCommerce.
	 *
	 * @return void
	 */
	public static function register_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Binance Payments', 'binance-personal-pay' ),
			__( 'Binance Payments', 'binance-personal-pay' ),
			'manage_woocommerce',
			'binance-payments',
			array( __CLASS__, 'render_transactions_page' )
		);
	}

	/**
	 * Enqueue admin scripts and stylesheets.
	 *
	 * @param string $hook_suffix Admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		$is_gateway_settings = isset( $_GET['section'] ) && 'binance_pay' === $_GET['section'];
		$is_transactions_page = 'woocommerce_page_binance-payments' === $hook_suffix;

		if ( ! $is_gateway_settings && ! $is_transactions_page ) {
			return;
		}

		$plugin_url = defined( 'BPP_PLUGIN_URL' ) ? BPP_PLUGIN_URL : plugins_url( '/', dirname( __FILE__ ) );
		$version    = defined( 'BPP_VERSION' ) ? BPP_VERSION : '0.1.0';

		wp_enqueue_style(
			'bpp-admin-style',
			$plugin_url . 'admin/css/admin.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'bpp-admin-script',
			$plugin_url . 'admin/js/admin.js',
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'bpp-admin-script',
			'bppAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bpp_admin_nonce' ),
				'strings' => array(
					'testing'       => __( 'Testing connection to Binance...', 'binance-personal-pay' ),
					'testSuccess'   => __( '✓ Connection verified successfully!', 'binance-personal-pay' ),
					'scanning'      => __( 'Scanning Binance Pay transactions...', 'binance-personal-pay' ),
					'scanCompleted' => __( 'Scan completed.', 'binance-personal-pay' ),
				),
			)
		);
	}

	/**
	 * Render the Test Connection button within the WooCommerce Payment Gateway settings.
	 *
	 * @param array $value Field attributes.
	 * @return void
	 */
	public static function render_test_connection_field( array $value ): void {
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'Test Binance Connection', 'binance-personal-pay' ); ?></label>
			</th>
			<td class="forminp">
				<button type="button" class="button button-secondary" id="bpp-btn-test-connection">
					<?php esc_html_e( 'Test Binance Connection', 'binance-personal-pay' ); ?>
				</button>
				<span class="spinner" id="bpp-test-spinner" style="float:none; margin:0 8px;"></span>
				<div id="bpp-test-result" style="margin-top:10px; font-weight:600; white-space:pre-line;"></div>
				<p class="description">
					<?php esc_html_e( 'Tests API signature generation, validates read-only Pay API access, and verifies merchant receiver configuration.', 'binance-personal-pay' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * AJAX handler for testing Binance API connectivity.
	 *
	 * @return void
	 */
	public static function handle_test_connection(): void {
		check_ajax_referer( 'bpp_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'binance-personal-pay' ) ), 403 );
		}

		$api_key     = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$api_secret  = isset( $_POST['api_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['api_secret'] ) ) : '';
		$merchant_id = isset( $_POST['merchant_binance_id'] ) ? sanitize_text_field( wp_unslash( $_POST['merchant_binance_id'] ) ) : '';

		// If form inputs are empty or masked, fall back to stored credentials.
		if ( empty( $api_key ) ) {
			$api_key = BPP_Settings::get_api_key();
		}
		if ( empty( $api_secret ) || str_contains( $api_secret, '•' ) ) {
			$api_secret = BPP_Settings::get_api_secret();
		}
		if ( empty( $merchant_id ) ) {
			$merchant_id = BPP_Settings::get_merchant_binance_id();
		}

		if ( empty( $api_key ) || empty( $api_secret ) || empty( $merchant_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your API Key, API Secret, and Binance ID before testing.', 'binance-personal-pay' ),
				)
			);
		}

		$api = new BPP_Binance_API( $api_key, $api_secret, $merchant_id );
		$res = $api->test_connection();

		if ( $res['success'] ) {
			wp_send_json_success( array( 'message' => $res['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $res['message'] ) );
		}
	}

	/**
	 * AJAX handler for triggering immediate transaction scan.
	 *
	 * @return void
	 */
	public static function handle_scan_now(): void {
		check_ajax_referer( 'bpp_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'binance-personal-pay' ) ), 403 );
		}

		$summary = BPP_Transaction_Scanner::scan_now( true );

		if ( ! $summary['success'] && ! empty( $summary['error_message'] ) ) {
			wp_send_json_error( array( 'message' => $summary['error_message'] ) );
		}

		$formatted = sprintf(
			/* translators: 1: Transactions found, 2: New transactions, 3: Matched, 4: Unmatched, 5: Errors */
			__( "Scan completed.\n\nTransactions found: %1\$d\nNew transactions: %2\$d\nMatched: %3\$d\nUnmatched: %4\$d\nErrors: %5\$d", 'binance-personal-pay' ),
			$summary['transactions_found'],
			$summary['new_transactions'],
			$summary['matched'],
			$summary['unmatched'],
			$summary['errors']
		);

		wp_send_json_success(
			array(
				'message' => $formatted,
				'summary' => $summary,
			)
		);
	}

	/**
	 * Render the Binance Payments admin transactions view.
	 *
	 * @return void
	 */
	public static function render_transactions_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'binance-personal-pay' ) );
		}

		include_once dirname( __FILE__ ) . '/views/transactions.php';
	}
}
