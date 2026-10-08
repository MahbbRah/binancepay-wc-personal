<?php
/**
 * Uninstallation cleanup for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Load dependencies required for cleanup.
require_once dirname( __FILE__ ) . '/database/class-schema.php';
require_once dirname( __FILE__ ) . '/includes/class-settings.php';
require_once dirname( __FILE__ ) . '/includes/class-scheduler.php';

// Only perform data deletion if the administrator explicitly enabled data cleanup on uninstall.
if ( BPP_Settings::delete_data_on_uninstall() ) {
	// 1. Unschedule Action Scheduler tasks.
	BPP_Scheduler::unschedule_scanner();

	// 2. Drop the custom transactions table.
	BPP_Schema::drop_tables();

	// 3. Remove plugin configuration options.
	delete_option( BPP_Settings::OPTION_NAME );
	delete_option( BPP_Schema::DB_VERSION_OPTION );

	// 4. Delete rate-limit transients.
	delete_transient( 'bpp_api_sync_lock' );
	delete_transient( 'bpp_scanner_last_run' );

	// Note: WooCommerce orders and general order data are NEVER deleted.
}
