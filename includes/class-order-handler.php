<?php
/**
 * WooCommerce order handler for Binance Personal Pay.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Order_Handler
 *
 * Manages deterministic payment references, order metadata, customer verification, and lifecycle updates.
 */
class BPP_Order_Handler {

	/**
	 * Meta key for payment reference note.
	 */
	public const META_PAYMENT_REFERENCE = '_bpp_payment_reference';

	/**
	 * Meta key for payment expiration timestamp.
	 */
	public const META_EXPIRES_AT = '_bpp_payment_expires_at';

	/**
	 * Meta key for matched Binance Pay Order ID.
	 */
	public const META_BINANCE_ORDER_ID = '_bpp_binance_order_id';

	/**
	 * Meta key for matched Binance Pay Transaction ID.
	 */
	public const META_BINANCE_TX_ID = '_bpp_binance_transaction_id';

	/**
	 * Meta key for timestamp of match.
	 */
	public const META_MATCHED_AT = '_bpp_matched_at';

	/**
	 * Meta key for payer details.
	 */
	public const META_PAYER_INFO = '_bpp_payer_info';

	/**
	 * Generate the deterministic payment reference: plan-{order_id}.
	 *
	 * @param WC_Order|int $order WooCommerce order or order ID.
	 * @return string
	 */
	public static function get_payment_reference( WC_Order|int $order ): string {
		$order_id = $order instanceof WC_Order ? $order->get_id() : (int) $order;
		return 'plan-' . $order_id;
	}

	/**
	 * Parse order ID from a given payment note/reference.
	 *
	 * @param string $reference Reference note e.g. 'plan-12345'.
	 * @return int|null Order ID if valid, null otherwise.
	 */
	public static function get_order_id_from_reference( string $reference ): ?int {
		$reference = trim( $reference );
		if ( preg_match( '/^plan-(\d+)$/i', $reference, $matches ) ) {
			return (int) $matches[1];
		}
		return null;
	}

	/**
	 * Initialize order metadata upon checkout placement.
	 *
	 * @param WC_Order $order WooCommerce order instance.
	 * @return void
	 */
	public static function initialize_order( WC_Order $order ): void {
		$order_id  = $order->get_id();
		$reference = self::get_payment_reference( $order_id );

		$timeout_minutes = BPP_Settings::get_payment_timeout_minutes();
		$expires_at      = time() + ( $timeout_minutes * 60 );

		$order->update_meta_data( self::META_PAYMENT_REFERENCE, $reference );
		$order->update_meta_data( self::META_EXPIRES_AT, $expires_at );
		$order->save();

		BPP_Logger::info( "Initialized Binance Pay for Order #{$order_id} with reference '{$reference}', expires in {$timeout_minutes} minutes." );
	}

	/**
	 * Get payment expiration timestamp for an order.
	 *
	 * @param WC_Order $order WooCommerce order instance.
	 * @return int
	 */
	public static function get_payment_expires_at( WC_Order $order ): int {
		$expires_at = (int) $order->get_meta( self::META_EXPIRES_AT, true );
		if ( $expires_at <= 0 ) {
			$created = $order->get_date_created();
			$created_ts = $created ? $created->getTimestamp() : time();
			$expires_at = $created_ts + ( BPP_Settings::get_payment_timeout_minutes() * 60 );
		}
		return $expires_at;
	}

	/**
	 * Check if an order is still within its valid payment window.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return bool
	 */
	public static function is_payment_window_active( WC_Order $order ): bool {
		return time() <= self::get_payment_expires_at( $order );
	}

	/**
	 * Check whether an order is payable with Binance Pay.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return bool
	 */
	public static function is_order_payable( WC_Order $order ): bool {
		// Needs to be pending or on-hold and unpaid.
		if ( $order->is_paid() ) {
			return false;
		}

		$status = $order->get_status();
		return in_array( $status, array( 'pending', 'on-hold', 'failed' ), true );
	}

	/**
	 * Authorize customer access to the payment status or payment page.
	 * Prevents IDOR (Insecure Direct Object Reference) vulnerabilities.
	 *
	 * @param WC_Order    $order     WooCommerce order.
	 * @param string|null $order_key Provided order key (from URL or request).
	 * @return bool True if authorized, false otherwise.
	 */
	public static function verify_order_access( WC_Order $order, ?string $order_key = null ): bool {
		// Admin / shop manager with capability can always inspect.
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		// Check order key.
		if ( ! empty( $order_key ) && hash_equals( $order->get_order_key(), trim( $order_key ) ) ) {
			return true;
		}

		// Check logged-in customer ownership.
		$current_user_id = get_current_user_id();
		if ( $current_user_id > 0 && $order->get_customer_id() === $current_user_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Mark order paid via standard WooCommerce payment_complete() with full audit note.
	 *
	 * @param WC_Order $order           WooCommerce order.
	 * @param array    $transaction_row Transaction details.
	 * @return bool True on success.
	 */
	public static function complete_order_payment( WC_Order $order, array $transaction_row ): bool {
		$tx_id    = $transaction_row['binance_transaction_id'] ?? '';
		$order_id = $order->get_id();

		if ( empty( $tx_id ) ) {
			return false;
		}

		// Prevent double-completion if already paid.
		if ( $order->is_paid() ) {
			BPP_Logger::warning( "Order #{$order_id} is already marked as paid. Ignoring duplicate completion attempt." );
			return true;
		}

		$binance_order_id = $transaction_row['binance_order_id'] ?? '';
		$amount           = $transaction_row['amount'] ?? '';
		$currency         = $transaction_row['currency'] ?? '';
		$note             = $transaction_row['note'] ?? '';
		$payer_name       = $transaction_row['payer_name'] ?? '';
		$payer_id         = $transaction_row['payer_binance_id'] ?? '';

		$order->update_meta_data( self::META_BINANCE_TX_ID, $tx_id );
		$order->update_meta_data( self::META_BINANCE_ORDER_ID, $binance_order_id );
		$order->update_meta_data( self::META_MATCHED_AT, current_time( 'mysql' ) );

		$payer_info = array(
			'name'       => $payer_name,
			'binance_id' => $payer_id,
		);
		$order->update_meta_data( self::META_PAYER_INFO, $payer_info );
		$order->save();

		// Add detailed order note.
		$note_text = sprintf(
			/* translators: 1: Amount, 2: Currency, 3: Transaction ID, 4: Binance Order ID, 5: Payment Note, 6: Payer */
			__( "Binance Pay payment verified.\n• Amount: %1\$s %2\$s\n• Binance Transaction ID: %3\$s\n• Binance Order ID: %4\$s\n• Note: %5\$s\n• Payer: %6\$s", 'binance-personal-pay' ),
			$amount,
			$currency,
			$tx_id,
			$binance_order_id,
			$note ?: __( '(None)', 'binance-personal-pay' ),
			$payer_name ?: ( $payer_id ?: __( 'Anonymous', 'binance-personal-pay' ) )
		);
		$order->add_order_note( $note_text );

		// Standard WooCommerce completion.
		$order->payment_complete( $tx_id );

		BPP_Logger::info( "Order #{$order_id} successfully marked complete via Binance transaction {$tx_id}." );

		return true;
	}
}
