<?php
/**
 * Transaction scanner and synchronizer for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Transaction_Scanner
 *
 * Efficiently polls Binance Pay transaction history in batches, ingests new records,
 * and reconciles them against pending WooCommerce orders.
 */
class BPP_Transaction_Scanner {

	/**
	 * Rate limit lock transient name.
	 */
	public const RATE_LIMIT_KEY = 'bpp_scanner_last_run';

	/**
	 * Run a scheduled scan triggered by Action Scheduler.
	 *
	 * @return void
	 */
	public static function run_scheduled_scan(): void {
		if ( ! BPP_Settings::is_auto_scan_enabled() ) {
			BPP_Logger::debug( 'Automatic background scan is disabled in settings.' );
			return;
		}

		self::scan_and_reconcile( false );
	}

	/**
	 * Run an immediate scan (triggered by admin 'Scan Now' or customer verification).
	 *
	 * @param bool $force_bypass_rate_limit Whether to bypass the short rate-limiting cooldown.
	 * @return array
	 */
	public static function scan_now( bool $force_bypass_rate_limit = false ): array {
		return self::scan_and_reconcile( $force_bypass_rate_limit );
	}

	/**
	 * Fetch transactions from Binance, ingest to local DB, and reconcile with pending orders.
	 *
	 * @param bool $force_bypass_rate_limit Bypass short sync cooldown.
	 * @return array Summary of scan.
	 */
	public static function scan_and_reconcile( bool $force_bypass_rate_limit = false ): array {
		global $wpdb;

		$summary = array(
			'success'            => false,
			'transactions_found' => 0,
			'new_transactions'   => 0,
			'matched'            => 0,
			'unmatched'          => 0,
			'errors'             => 0,
			'error_message'      => '',
		);

		// Check API credentials.
		$api_key     = BPP_Settings::get_api_key();
		$api_secret  = BPP_Settings::get_api_secret();
		$merchant_id = BPP_Settings::get_merchant_binance_id();

		if ( empty( $api_key ) || empty( $api_secret ) || empty( $merchant_id ) ) {
			$summary['error_message'] = __( 'Binance credentials or Binance ID are missing.', 'binance-personal-pay' );
			BPP_Logger::warning( 'Scanner aborted: Binance credentials or ID missing.' );
			return $summary;
		}

		// Rate limit protection (minimum 5s between calls unless forced).
		$last_run = (int) get_transient( self::RATE_LIMIT_KEY );
		if ( ! $force_bypass_rate_limit && ( time() - $last_run ) < BPP_Binance_API::MIN_SYNC_INTERVAL_SEC ) {
			BPP_Logger::debug( 'Scanner called too quickly; serving reconciliation from existing local transactions.' );
			// Even if API call is throttled, run reconciliation against pending orders from existing local transactions.
			$reconcile_results = self::reconcile_pending_orders();
			$summary['success']   = true;
			$summary['matched']   = $reconcile_results['matched'];
			$summary['unmatched'] = $reconcile_results['unmatched'];
			return $summary;
		}

		set_transient( self::RATE_LIMIT_KEY, time(), 30 );

		// Calculate lookback window in milliseconds.
		$lookback_mins = BPP_Settings::get_lookback_minutes();
		$start_time_ms = ( time() - ( $lookback_mins * 60 ) ) * 1000;

		$api = new BPP_Binance_API( $api_key, $api_secret, $merchant_id );
		$res = $api->get_pay_transactions( $start_time_ms, null, 100 );

		if ( ! $res['success'] ) {
			$summary['errors']        = 1;
			$summary['error_message'] = $res['error'];
			BPP_Logger::error( "Transaction scan failed: {$res['error']}" );
			return $summary;
		}

		$transactions               = $res['transactions'];
		$summary['transactions_found'] = count( $transactions );

		// Ingest into local table.
		$table_name = BPP_Schema::get_table_name();
		$now_mysql  = current_time( 'mysql' );

		foreach ( $transactions as $tx ) {
			$tx_id = $tx['binance_transaction_id'];

			// Check if already stored.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE binance_transaction_id = %s",
					$tx_id
				)
			);

			if ( $exists ) {
				continue;
			}

			// Insert new transaction.
			$insert_data = array(
				'binance_transaction_id' => $tx['binance_transaction_id'],
				'binance_order_id'       => $tx['binance_order_id'],
				'woocommerce_order_id'   => null,
				'amount'                 => $tx['amount'],
				'currency'               => $tx['currency'],
				'note'                   => $tx['note'],
				'transaction_time'       => $tx['transaction_time'],
				'wallet_type'            => $tx['wallet_type'],
				'order_type'             => $tx['order_type'],
				'payer_binance_id'       => $tx['payer_binance_id'],
				'payer_name'             => $tx['payer_name'],
				'receiver_binance_id'    => $tx['receiver_binance_id'],
				'raw_response'           => $tx['raw_response'],
				'status'                 => BPP_Payment_Matcher::STATUS_DISCOVERED,
				'matched_at'             => null,
				'created_at'             => $now_mysql,
				'updated_at'             => $now_mysql,
			);

			$wpdb->insert(
				$table_name,
				$insert_data,
				array( '%s', '%s', null, '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', null, '%s', '%s' )
			);

			$summary['new_transactions']++;
		}

		BPP_Logger::info( "Scanned Binance Pay: Found {$summary['transactions_found']} txs, Ingested {$summary['new_transactions']} new txs." );

		// Reconcile pending WooCommerce orders.
		$reconcile_results = self::reconcile_pending_orders();
		$summary['matched']   = $reconcile_results['matched'];
		$summary['unmatched'] = $reconcile_results['unmatched'];
		$summary['success']   = true;

		return $summary;
	}

	/**
	 * Reconcile all pending WooCommerce orders against stored transactions.
	 *
	 * @return array{matched: int, unmatched: int}
	 */
	public static function reconcile_pending_orders(): array {
		$results = array(
			'matched'   => 0,
			'unmatched' => 0,
		);

		// Find pending Binance Pay orders.
		$order_ids = wc_get_orders(
			array(
				'payment_method' => 'binance_pay',
				'status'         => array( 'pending', 'on-hold' ),
				'limit'          => 50,
				'return'         => 'ids',
			)
		);

		if ( empty( $order_ids ) ) {
			return $results;
		}

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			// Attempt note matching.
			$match = BPP_Payment_Matcher::match_order_by_note( $order );
			if ( $match['matched'] ) {
				$results['matched']++;
			} else {
				$results['unmatched']++;
			}
		}

		return $results;
	}
}
