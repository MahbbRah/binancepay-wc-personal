<?php
/**
 * Payment reconciliation and verification matcher for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Payment_Matcher
 *
 * Independently validates Binance Pay transactions against WooCommerce orders.
 */
class BPP_Payment_Matcher {

	/**
	 * Status constants for transactions.
	 */
	public const STATUS_DISCOVERED      = 'discovered';
	public const STATUS_MATCHED         = 'matched';
	public const STATUS_PROCESSED       = 'processed';
	public const STATUS_UNMATCHED       = 'unmatched';
	public const STATUS_AMOUNT_MISMATCH = 'amount_mismatch';
	public const STATUS_NOTE_MISMATCH   = 'note_mismatch';
	public const STATUS_DUPLICATE       = 'duplicate';
	public const STATUS_IGNORED         = 'ignored';
	public const STATUS_ERROR           = 'error';

	/**
	 * Verify and match a transaction against a specific WooCommerce order.
	 *
	 * @param array    $transaction      Stored transaction row or parsed transaction array.
	 * @param WC_Order $order            Target WooCommerce order.
	 * @param bool     $is_fallback_mode Whether verification is using customer-provided Binance Order ID.
	 * @return array{matched: bool, reason: string, transaction: array}
	 */
	public static function verify_and_match( array $transaction, WC_Order $order, bool $is_fallback_mode = false ): array {
		global $wpdb;

		$order_id            = $order->get_id();
		$configured_receiver = BPP_Settings::get_merchant_binance_id();
		$order_currency      = $order->get_currency();
		$expected_currency   = BPP_Settings::get_currency();
		$order_total         = (string) $order->get_total();
		$expected_reference  = BPP_Order_Handler::get_payment_reference( $order_id );

		$tx_id          = $transaction['binance_transaction_id'] ?? '';
		$tx_order_id    = $transaction['binance_order_id'] ?? '';
		$tx_amount      = $transaction['amount'] ?? '0';
		$tx_currency    = strtoupper( trim( (string) ( $transaction['currency'] ?? '' ) ) );
		$tx_receiver    = trim( (string) ( $transaction['receiver_binance_id'] ?? '' ) );
		$tx_note        = trim( (string) ( $transaction['note'] ?? '' ) );
		$tx_time_ms     = (int) ( $transaction['transaction_time'] ?? 0 );
		$tx_time_sec    = (int) ( $tx_time_ms / 1000 );

		// 1. Transaction Uniqueness & Idempotency.
		$table_name = BPP_Schema::get_table_name();
		// Check if transaction has already been processed for another order.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, woocommerce_order_id FROM {$table_name} WHERE binance_transaction_id = %s",
				$tx_id
			),
			ARRAY_A
		);

		if ( $existing && self::STATUS_PROCESSED === $existing['status'] ) {
			if ( (int) $existing['woocommerce_order_id'] !== $order_id ) {
				BPP_Logger::warning( "Transaction {$tx_id} already consumed for Order #{$existing['woocommerce_order_id']}." );
				self::update_transaction_status( $tx_id, self::STATUS_DUPLICATE );
				return array(
					'matched'     => false,
					'reason'      => __( 'This transaction has already been processed for another order.', 'binance-personal-pay' ),
					'transaction' => $transaction,
				);
			}
			// Already processed for this exact order.
			return array(
				'matched'     => true,
				'reason'      => __( 'Transaction already verified and processed for this order.', 'binance-personal-pay' ),
				'transaction' => $transaction,
			);
		}

		// 2. Incoming Transaction Direction: Amount > 0 AND Receiver == configured Binance ID.
		if ( BPP_Money::compare( $tx_amount, '0' ) <= 0 ) {
			self::update_transaction_status( $tx_id, self::STATUS_IGNORED );
			return array(
				'matched'     => false,
				'reason'      => __( 'Transaction is not an incoming payment (amount <= 0).', 'binance-personal-pay' ),
				'transaction' => $transaction,
			);
		}

		if ( '' === $configured_receiver || $tx_receiver !== $configured_receiver ) {
			self::update_transaction_status( $tx_id, self::STATUS_IGNORED );
			return array(
				'matched'     => false,
				'reason'      => sprintf(
					/* translators: 1: Received Binance ID, 2: Configured Binance ID */
					__( 'Receiver mismatch: Received by %1$s, expected merchant %2$s.', 'binance-personal-pay' ),
					$tx_receiver,
					$configured_receiver
				),
				'transaction' => $transaction,
			);
		}

		// 3. Currency Validation.
		// Enforce that WooCommerce order is in USD currency.
		if ( 'USD' !== strtoupper( $order_currency ) ) {
			BPP_Logger::warning( "Order #{$order_id} currency is not USD (current: {$order_currency}). Binance Pay requires USD orders." );
			return array(
				'matched'     => false,
				'reason'      => sprintf(
					/* translators: %s: Order currency */
					__( 'Order currency must be USD (current: %s). Binance Pay only supports USD orders.', 'binance-personal-pay' ),
					$order_currency
				),
				'transaction' => $transaction,
			);
		}

		// Accepted Binance crypto currencies for USD order: configured crypto (e.g. USDT) or USD/USDC.
		$accepted_currencies = array( strtoupper( $expected_currency ) );
		if ( 'USDT' === strtoupper( $expected_currency ) ) {
			$accepted_currencies[] = 'USD';
			$accepted_currencies[] = 'USDC';
		}

		if ( ! in_array( $tx_currency, $accepted_currencies, true ) ) {
			BPP_Logger::warning( "Currency mismatch for tx {$tx_id}: got {$tx_currency}, expected {$expected_currency}." );
			return array(
				'matched'     => false,
				'reason'      => sprintf(
					/* translators: 1: Transaction currency, 2: Expected currency */
					__( 'Currency mismatch: received %1$s, expected %2$s.', 'binance-personal-pay' ),
					$tx_currency,
					$expected_currency
				),
				'transaction' => $transaction,
			);
		}

		// 4. Amount Validation (String/Decimal-based equality, no float comparisons!).
		if ( ! BPP_Money::is_equal( $tx_amount, $order_total, $tx_currency ) ) {
			BPP_Logger::warning( "Amount mismatch for Order #{$order_id}: received {$tx_amount} {$tx_currency}, expected {$order_total} {$tx_currency}." );
			self::update_transaction_status( $tx_id, self::STATUS_AMOUNT_MISMATCH, $order_id );
			return array(
				'matched'     => false,
				'reason'      => sprintf(
					/* translators: 1: Received amount, 2: Expected amount, 3: Currency */
					__( 'Amount mismatch: received %1$s %3$s, expected %2$s %3$s.', 'binance-personal-pay' ),
					$tx_amount,
					$order_total,
					$tx_currency
				),
				'transaction' => $transaction,
			);
		}

		// 5. Payment Reference / Fallback Order ID.
		if ( $is_fallback_mode ) {
			// In fallback mode, the customer provided their Binance Pay orderId because note was missing/wrong.
			// The caller ensured $tx_order_id matches the submitted Binance Order ID.
			BPP_Logger::info( "Verifying transaction {$tx_id} via Binance Order ID fallback ({$tx_order_id}) for Order #{$order_id}." );
		} else {
			// Normal automatic or note matching: note must match plan-{order_id}.
			if ( strtolower( $tx_note ) !== strtolower( $expected_reference ) ) {
				BPP_Logger::info( "Note mismatch for tx {$tx_id}: got '{$tx_note}', expected '{$expected_reference}'." );
				self::update_transaction_status( $tx_id, self::STATUS_NOTE_MISMATCH );
				return array(
					'matched'     => false,
					'reason'      => sprintf(
						/* translators: 1: Received note, 2: Expected note */
						__( 'Note mismatch: received "%1$s", expected "%2$s".', 'binance-personal-pay' ),
						$tx_note,
						$expected_reference
					),
					'transaction' => $transaction,
				);
			}
		}

		// 6. Payment Time Window.
		// Transaction time must not pre-date order creation (with 300 second clock-skew buffer).
		$order_date = $order->get_date_created();
		$order_created_sec = $order_date ? $order_date->getTimestamp() : time();
		$earliest_allowed_sec = $order_created_sec - 300; // 5 minute clock drift buffer

		if ( $tx_time_sec > 0 && $tx_time_sec < $earliest_allowed_sec ) {
			BPP_Logger::warning( "Transaction {$tx_id} timestamp ({$tx_time_sec}) occurred before Order #{$order_id} was created ({$order_created_sec})." );
			return array(
				'matched'     => false,
				'reason'      => __( 'Transaction occurred before order was placed.', 'binance-personal-pay' ),
				'transaction' => $transaction,
			);
		}

		// 7. Success! Commit transaction as PROCESSED and complete order.
		self::update_transaction_status( $tx_id, self::STATUS_PROCESSED, $order_id, current_time( 'mysql' ) );
		BPP_Order_Handler::complete_order_payment( $order, $transaction );

		return array(
			'matched'     => true,
			'reason'      => __( 'Payment verified successfully.', 'binance-personal-pay' ),
			'transaction' => $transaction,
		);
	}

	/**
	 * Find and match an incoming transaction for an order based on payment note plan-{order_id}.
	 *
	 * @param WC_Order $order Target order.
	 * @return array{matched: bool, reason: string, transaction: ?array}
	 */
	public static function match_order_by_note( WC_Order $order ): array {
		global $wpdb;

		$order_id           = $order->get_id();
		$expected_reference = BPP_Order_Handler::get_payment_reference( $order_id );
		$table_name         = BPP_Schema::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$candidate = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE note = %s AND status IN (%s, %s, %s) ORDER BY id DESC LIMIT 1",
				$expected_reference,
				self::STATUS_DISCOVERED,
				self::STATUS_UNMATCHED,
				self::STATUS_NOTE_MISMATCH
			),
			ARRAY_A
		);

		if ( ! $candidate ) {
			return array(
				'matched'     => false,
				'reason'      => __( 'No transaction found matching payment note.', 'binance-personal-pay' ),
				'transaction' => null,
			);
		}

		return self::verify_and_match( $candidate, $order, false );
	}

	/**
	 * Find and match a transaction by Binance Pay Order ID fallback.
	 *
	 * @param string   $binance_order_id Customer-submitted Binance Pay Order ID.
	 * @param WC_Order $order            Target WooCommerce order.
	 * @return array{matched: bool, reason: string, transaction: ?array}
	 */
	public static function match_order_by_binance_order_id( string $binance_order_id, WC_Order $order ): array {
		global $wpdb;

		$binance_order_id = trim( $binance_order_id );
		if ( empty( $binance_order_id ) ) {
			return array(
				'matched'     => false,
				'reason'      => __( 'Binance Order ID cannot be empty.', 'binance-personal-pay' ),
				'transaction' => null,
			);
		}

		$table_name = BPP_Schema::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$candidate = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE binance_order_id = %s ORDER BY id DESC LIMIT 1",
				$binance_order_id
			),
			ARRAY_A
		);

		if ( ! $candidate ) {
			return array(
				'matched'     => false,
				'reason'      => __( 'Binance Order ID not found in transaction history.', 'binance-personal-pay' ),
				'transaction' => null,
			);
		}

		return self::verify_and_match( $candidate, $order, true );
	}

	/**
	 * Update transaction status and association in database.
	 *
	 * @param string      $binance_tx_id Transaction ID.
	 * @param string      $status        New status.
	 * @param int|null    $order_id      Optional WooCommerce Order ID.
	 * @param string|null $matched_at    Optional match datetime string.
	 * @return void
	 */
	public static function update_transaction_status( string $binance_tx_id, string $status, ?int $order_id = null, ?string $matched_at = null ): void {
		global $wpdb;

		$table_name = BPP_Schema::get_table_name();
		$data       = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		$formats    = array( '%s', '%s' );

		if ( null !== $order_id ) {
			$data['woocommerce_order_id'] = $order_id;
			$formats[]                   = '%d';
		}

		if ( null !== $matched_at ) {
			$data['matched_at'] = $matched_at;
			$formats[]          = '%s';
		}

		$wpdb->update(
			$table_name,
			$data,
			array( 'binance_transaction_id' => $binance_tx_id ),
			$formats,
			array( '%s' )
		);
	}
}
