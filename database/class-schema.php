<?php
/**
 * Database schema management for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Schema
 *
 * Handles creation, updates, and maintenance of plugin database tables.
 */
class BPP_Schema {

	/**
	 * Database version option key.
	 */
	public const DB_VERSION_OPTION = 'bpp_db_version';

	/**
	 * Current database schema version.
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Get the transaction table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'bpp_transactions';
	}

	/**
	 * Create or update plugin database tables using dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			binance_transaction_id VARCHAR(64) NOT NULL,
			binance_order_id VARCHAR(64) NOT NULL,
			woocommerce_order_id BIGINT(20) UNSIGNED NULL,
			amount VARCHAR(32) NOT NULL,
			currency VARCHAR(16) NOT NULL,
			note VARCHAR(255) NOT NULL DEFAULT '',
			transaction_time BIGINT(20) UNSIGNED NOT NULL,
			wallet_type VARCHAR(32) NOT NULL DEFAULT '',
			order_type VARCHAR(32) NOT NULL DEFAULT '',
			payer_binance_id VARCHAR(64) NOT NULL DEFAULT '',
			payer_name VARCHAR(255) NOT NULL DEFAULT '',
			receiver_binance_id VARCHAR(64) NOT NULL DEFAULT '',
			receiver_account_id VARCHAR(64) NOT NULL DEFAULT '',
			raw_response LONGTEXT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'discovered',
			matched_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY binance_transaction_id (binance_transaction_id),
			KEY binance_order_id (binance_order_id),
			KEY note (note),
			KEY woocommerce_order_id (woocommerce_order_id),
			KEY transaction_time (transaction_time),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Drop plugin database tables on full uninstall.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;
		$table_name = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		delete_option( self::DB_VERSION_OPTION );
	}
}
