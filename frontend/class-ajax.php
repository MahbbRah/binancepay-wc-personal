<?php
/**
 * AJAX handlers for customer payment page interactions.
 *
 * @package BinancePersonalPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BPP_Ajax
 *
 * Handles status polling, manual verification triggers, and Binance Order ID fallbacks.
 */
class BPP_Ajax {

	/**
	 * Register AJAX actions for both logged-in and guest customers.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Browser automatic polling.
		add_action( 'wp_ajax_bpp_check_payment_status', array( __CLASS__, 'check_payment_status' ) );
		add_action( 'wp_ajax_nopriv_bpp_check_payment_status', array( __CLASS__, 'check_payment_status' ) );

		// Manual "I've Completed Payment" trigger.
		add_action( 'wp_ajax_bpp_manual_verify', array( __CLASS__, 'manual_verify' ) );
		add_action( 'wp_ajax_nopriv_bpp_manual_verify', array( __CLASS__, 'manual_verify' ) );

		// Binance Order ID fallback verification.
		add_action( 'wp_ajax_bpp_fallback_verify', array( __CLASS__, 'fallback_verify' ) );
		add_action( 'wp_ajax_nopriv_bpp_fallback_verify', array( __CLASS__, 'fallback_verify' ) );
	}

	/**
	 * Verify customer request security (nonce and order access authorization).
	 *
	 * @return WC_Order Order instance if valid; terminates request if invalid.
	 */
	private static function authorize_request(): WC_Order {
		check_ajax_referer( 'bpp_payment_nonce', 'security' );

		$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

		if ( $order_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Missing order ID.', 'binance-personal-pay' ) ), 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'binance-personal-pay' ) ), 404 );
		}

		if ( ! BPP_Order_Handler::verify_order_access( $order, $order_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access to this order.', 'binance-personal-pay' ) ), 403 );
		}

		return $order;
	}

	/**
	 * Polling endpoint: checks order payment status.
	 *
	 * @return void
	 */
	public static function check_payment_status(): void {
		$order = self::authorize_request();

		// Check if already marked as paid.
		if ( $order->is_paid() || in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			wp_send_json_success(
				array(
					'status'       => 'paid',
					'redirect_url' => $order->get_checkout_order_received_url(),
				)
			);
		}

		$expires_at = BPP_Order_Handler::get_payment_expires_at( $order );
		$time_left  = max( 0, $expires_at - time() );

		// If time expired, invoke timeout policy.
		if ( 0 === $time_left ) {
			$timeout_res = BPP_Payment_Status::handle_timeout( $order );
			wp_send_json_success(
				array(
					'status'       => $timeout_res['status'],
					'redirect_url' => $timeout_res['redirect_url'] ?? '',
					'message'      => $timeout_res['message'] ?? '',
					'time_left'    => 0,
				)
			);
		}

		wp_send_json_success(
			array(
				'status'       => 'pending',
				'time_left'    => $time_left,
				'redirect_url' => '',
			)
		);
	}

	/**
	 * Manual "I've Completed Payment" verification handler.
	 *
	 * @return void
	 */
	public static function manual_verify(): void {
		$order = self::authorize_request();

		// If already paid, return immediately.
		if ( $order->is_paid() ) {
			wp_send_json_success(
				array(
					'status'       => 'paid',
					'redirect_url' => $order->get_checkout_order_received_url(),
					'message'      => __( 'Payment verified successfully.', 'binance-personal-pay' ),
				)
			);
		}

		// 1. Trigger immediate server-side sync with Binance.
		BPP_Transaction_Scanner::scan_now( false );

		// 2. Perform note matching against ingested transactions.
		$match = BPP_Payment_Matcher::match_order_by_note( $order );

		if ( $match['matched'] ) {
			wp_send_json_success(
				array(
					'status'       => 'paid',
					'redirect_url' => $order->get_checkout_order_received_url(),
					'message'      => __( '✓ Payment verified! Redirecting...', 'binance-personal-pay' ),
				)
			);
		}

		// Not found yet. Return message without failing order.
		wp_send_json_error(
			array(
				'status'  => 'pending',
				'message' => __( "Payment not detected yet. We'll continue checking automatically.", 'binance-personal-pay' ),
			)
		);
	}

	/**
	 * Binance Pay Order ID fallback handler.
	 *
	 * @return void
	 */
	public static function fallback_verify(): void {
		$order = self::authorize_request();

		$binance_order_id = isset( $_POST['binance_order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['binance_order_id'] ) ) : '';

		if ( empty( $binance_order_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your Binance Order ID.', 'binance-personal-pay' ),
				),
				400
			);
		}

		// If already paid, return immediately.
		if ( $order->is_paid() ) {
			wp_send_json_success(
				array(
					'status'       => 'paid',
					'redirect_url' => $order->get_checkout_order_received_url(),
					'message'      => __( 'Payment verified successfully.', 'binance-personal-pay' ),
				)
			);
		}

		// 1. Try matching with currently stored local transactions.
		$match = BPP_Payment_Matcher::match_order_by_binance_order_id( $binance_order_id, $order );

		// 2. If not found locally, trigger immediate sync with Binance and retry match.
		if ( ! $match['matched'] ) {
			BPP_Transaction_Scanner::scan_now( false );
			$match = BPP_Payment_Matcher::match_order_by_binance_order_id( $binance_order_id, $order );
		}

		if ( $match['matched'] ) {
			wp_send_json_success(
				array(
					'status'       => 'paid',
					'redirect_url' => $order->get_checkout_order_received_url(),
					'message'      => __( '✓ Payment verified! Redirecting...', 'binance-personal-pay' ),
				)
			);
		}

		wp_send_json_error(
			array(
				'status'  => 'pending',
				'message' => $match['reason'] ?: __( 'Payment could not be verified with this Binance Order ID.', 'binance-personal-pay' ),
			)
		);
	}
}
